package com.example.service;

import com.azure.storage.blob.BlobClientBuilder;
import com.azure.storage.blob.BlobClient;
import com.azure.storage.queue.QueueClientBuilder;
import com.azure.storage.queue.QueueClient;
import com.fasterxml.jackson.databind.ObjectMapper;
import io.github.cdimascio.dotenv.Dotenv;
import org.springframework.stereotype.Service;
import org.springframework.web.multipart.MultipartFile;

import java.io.IOException;
import java.time.Instant;
import java.util.Base64;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;
/*
@Service
public class AzureStorageService {
    private final String connectionString;
    private final String containerName;
    private final String queueName;
    private final ObjectMapper objectMapper;

    public AzureStorageService() {
        // Load environment variables
        Dotenv dotenv = Dotenv.load();
        
        String accountName = dotenv.get("AZURE_STORAGE_ACCOUNT");
        String accountKey = dotenv.get("AZURE_STORAGE_KEY");
        this.containerName = dotenv.get("AZURE_BLOB_CONTAINER");
        this.queueName = dotenv.get("AZURE_QUEUE_NAME");
        
        // Construct connection string
        this.connectionString = String.format(
            "DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s;EndpointSuffix=core.windows.net",
            accountName,
            accountKey
        );
        
        this.objectMapper = new ObjectMapper();
    }

    public String uploadFile(MultipartFile file, String dimensions) throws IOException {
        // Validate file
        if (file.isEmpty()) {
            throw new IllegalArgumentException("File is empty");
        }

        // Validate file type
        String contentType = file.getContentType();
        if (contentType == null || !isAllowedFileType(contentType)) {
            throw new IllegalArgumentException("Invalid file type. Only JPEG, PNG, and GIF are allowed.");
        }

        // Generate unique filename
        String fileName = UUID.randomUUID().toString() + "_" + file.getOriginalFilename();

        // Create blob client
        BlobClient blobClient = new BlobClientBuilder()
            .connectionString(connectionString)
            .containerName(containerName)
            .blobName(fileName)
            .buildClient();

        // Upload file
        blobClient.upload(file.getInputStream(), file.getSize(), true);

        // Get blob URL
        String blobUrl = blobClient.getBlobUrl();

        // Create queue message
        QueueClient queueClient = new QueueClientBuilder()
            .connectionString(connectionString)
            .queueName(queueName)
            .buildClient();

        // Prepare message content
        Map<String, Object> messageContent = new HashMap<>();
        messageContent.put("blobUrl", blobUrl);
        messageContent.put("dimensions", dimensions);
        messageContent.put("timestamp", Instant.now().getEpochSecond());

        // Send message to queue
        String messageJson = objectMapper.writeValueAsString(messageContent);
        queueClient.sendMessage(Base64.getEncoder().encodeToString(messageJson.getBytes()));

        return blobUrl;
    }

    private boolean isAllowedFileType(String contentType) {
        return contentType.equals("image/jpeg") ||
               contentType.equals("image/png") ||
               contentType.equals("image/gif");
    }
}