import logging
import azure.functions as func
from azure.storage.blob import BlobServiceClient
from azure.data.tables import TableServiceClient, TableEntity
from azure.core.exceptions import AzureError
from PIL import Image
import io
import os
from datetime import datetime

# Configuration
STORAGE_ACCOUNT_NAME = os.getenv("STORAGE_ACCOUNT_NAME")
STORAGE_ACCOUNT_KEY = os.getenv("STORAGE_ACCOUNT_KEY")
STORAGE_CONNECTION_STRING = os.getenv("STORAGE_CONNECTION_STRING")
CONTAINER_ORIGINAL = "original-images"
CONTAINER_PROCESSED = "processed-images"
TABLE_NAME = "ImageProcessing"

class ImageProcessor:
    def __init__(self):
        self.blob_service_client = BlobServiceClient.from_connection_string(
            STORAGE_CONNECTION_STRING
        )
        self.table_service_client = TableServiceClient.from_connection_string(
            STORAGE_CONNECTION_STRING
        )
        self.table_client = self.table_service_client.get_table_client(TABLE_NAME)

    def get_blob_client(self, container_name, blob_name):
        return self.blob_service_client.get_container_client(
            container_name
        ).get_blob_client(blob_name)

    def download_image(self, image_path):
        try:
            blob_client = self.get_blob_client(CONTAINER_ORIGINAL, image_path)
            return io.BytesIO(blob_client.download_blob().readall())
        except AzureError as e:
            logging.error(f"Erreur lors du téléchargement de l'image: {str(e)}")
            raise

    def upload_image(self, image_stream, new_image_path):
        try:
            blob_client = self.get_blob_client(CONTAINER_PROCESSED, new_image_path)
            image_stream.seek(0)
            blob_client.upload_blob(image_stream, overwrite=True)
            return new_image_path
        except AzureError as e:
            logging.error(f"Erreur lors de l'upload de l'image: {str(e)}")
            raise

    def resize_image(self, image_path, width, height):
        try:
            # Téléchargement de l'image
            image_stream = self.download_image(image_path)
            image = Image.open(image_stream)

            # Redimensionnement
            image_resized = image.resize((width, height), Image.Resampling.LANCZOS)

            # Préparation pour l'upload
            output_stream = io.BytesIO()
            image_resized.save(output_stream, format=image.format)

            # Upload de l'image redimensionnée
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            new_image_path = f"resized_{timestamp}_{image_path}"
            return self.upload_image(output_stream, new_image_path)

        except Exception as e:
            logging.error(f"Erreur lors du redimensionnement: {str(e)}")
            raise

    def save_to_table(self, original_path, resized_path, width, height, status="completed"):
        try:
            entity = TableEntity()
            entity["PartitionKey"] = "Images"
            entity["RowKey"] = datetime.now().strftime("%Y%m%d_%H%M%S")
            entity["OriginalPath"] = original_path
            entity["ResizedPath"] = resized_path
            entity["Width"] = width
            entity["Height"] = height
            entity["Status"] = status
            entity["ProcessedDate"] = datetime.utcnow().isoformat()

            self.table_client.upsert_entity(entity)
            logging.info(f"Informations sauvegardées pour l'image: {resized_path}")

        except AzureError as e:
            logging.error(f"Erreur lors de la sauvegarde dans Table Storage: {str(e)}")
            raise

def main(req: func.HttpRequest) -> func.HttpResponse:
    logging.info("Démarrage du traitement d'image")

    try:
        # Validation des données d'entrée
        req_body = req.get_json()
        image_path = req_body.get("image_path")
        width = int(req_body.get("width", 800))
        height = int(req_body.get("height", 600))

        if not image_path:
            return func.HttpResponse(
                "Le paramètre image_path est requis",
                status_code=400
            )

        # Initialisation et traitement
        processor = ImageProcessor()
        resized_path = processor.resize_image(image_path, width, height)
        processor.save_to_table(image_path, resized_path, width, height)

        return func.HttpResponse(
            f"Image traitée avec succès. Nouvelle image: {resized_path}",
            status_code=200
        )

    except ValueError as e:
        logging.error(f"Erreur de validation: {str(e)}")
        return func.HttpResponse(str(e), status_code=400)
    except Exception as e:
        logging.error(f"Erreur interne: {str(e)}")
        return func.HttpResponse(
            "Une erreur est survenue lors du traitement",
            status_code=500
        )

# Si besoin d'exécuter localement pour tests
if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)