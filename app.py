from flask import Flask, render_template, request, flash, redirect, url_for
from azure.storage.blob import BlobServiceClient
from azure.storage.queue import QueueServiceClient
import os
from PIL import Image
import json
from datetime import datetime

app = Flask(__name__)
app.secret_key = os.environ.get('FLASK_SECRET_KEY', 'default-secret-key')

# Azure credentials from environment variables
CONNECTION_STRING = os.environ.get('AZURE_STORAGE_CONNECTION_STRING')
CONTAINER_NAME = os.environ.get('AZURE_CONTAINER_NAME', 'images')
QUEUE_NAME = os.environ.get('AZURE_QUEUE_NAME', 'image-metadata')

@app.route('/')
def index():
    return render_template('upload.html')

@app.route('/upload', methods=['POST'])
def upload():
    try:
        if 'image' not in request.files:
            flash('No image file uploaded')
            return redirect(url_for('index'))

        image_file = request.files['image']
        width = request.form.get('width', '')
        height = request.form.get('height', '')

        if image_file.filename == '':
            flash('No selected file')
            return redirect(url_for('index'))

        # Initialize Azure clients
        blob_service_client = BlobServiceClient.from_connection_string(CONNECTION_STRING)
        container_client = blob_service_client.get_container_client(CONTAINER_NAME)
        queue_client = QueueServiceClient.from_connection_string(CONNECTION_STRING).get_queue_client(QUEUE_NAME)

        # Generate unique blob name
        blob_name = f"{datetime.utcnow().strftime('%Y%m%d-%H%M%S')}-{image_file.filename}"
        blob_client = container_client.get_blob_client(blob_name)

        # Upload to blob storage
        image_file.seek(0)
        blob_client.upload_blob(image_file)

        # Get blob URL
        blob_url = blob_client.url

        # Create message for queue
        message = {
            'image_path': blob_url,
            'width': width,
            'height': height,
            'upload_time': datetime.utcnow().isoformat()
        }

        # Add message to queue
        queue_client.send_message(json.dumps(message))

        flash('Upload successful!')
        return redirect(url_for('index'))

    except Exception as e:
        flash(f'Error: {str(e)}')
        return redirect(url_for('index'))

if __name__ == '__main__':
    app.run(debug=True)