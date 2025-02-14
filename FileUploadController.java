package com.example.controller;

import com.example.service.AzureStorageService;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.multipart.MultipartFile;

import java.util.HashMap;
import java.util.Map;

@RestController
@RequestMapping("/api")
public class FileUploadController {
    private final AzureStorageService storageService;

    public FileUploadController(AzureStorageService storageService) {
        this.storageService = storageService;
    }

    @PostMapping("/upload")
    public ResponseEntity<?> uploadFile(
            @RequestParam("file") MultipartFile file,
            @RequestParam("dimensions") String dimensions) {
        try {
            String blobUrl = storageService.uploadFile(file, dimensions);
            
            Map<String, Object> response = new HashMap<>();
            response.put("status", "success");
            response.put("message", "File uploaded and message sent to queue");
            response.put("blobUrl", blobUrl);
            
            return ResponseEntity.ok(response);
        } catch (IllegalArgumentException e) {
            Map<String, Object> response = new HashMap<>();
            response.put("status", "error");
            response.put("message", e.getMessage());
            
            return ResponseEntity.badRequest().body(response);
        } catch (Exception e) {
            Map<String, Object> response = new HashMap<>();
            response.put("status", "error");
            response.put("message", "An error occurred while uploading the file: " + e.getMessage());
            
            return ResponseEntity.internalServerError().body(response);
        }
    }
}