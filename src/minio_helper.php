<?php
class MinioClient {
    private string $endpoint;
    private string $accessKey;
    private string $secretKey;
    private string $bucket;
    private string $region;

    public function __construct() {
        $this->endpoint  = getenv('MINIO_ENDPOINT') ?: 'minio:9000';
        $this->accessKey = getenv('MINIO_ACCESS_KEY') ?: 'minioadmin';
        $this->secretKey = getenv('MINIO_SECRET_KEY') ?: 'minioadmin123';
        $this->bucket    = getenv('MINIO_BUCKET') ?: 'project-files';
        $this->region    = 'us-east-1';
    }

    public function getBucket(): string {
        return $this->bucket;
    }

    /**
     * Tải file lên MinIO Object Storage
     */
    public function uploadObject(string $objectKey, string $fileContent, string $contentType): bool {
        return $this->sendRequest('PUT', $objectKey, $fileContent, $contentType);
    }

    /**
     * Lấy dữ liệu file từ MinIO
     */
    public function getObject(string $objectKey): ?array {
        $ch = curl_init();
        $url = "http://{$this->endpoint}/{$this->bucket}/{$objectKey}";
        $headers = $this->createSignedHeaders('GET', $objectKey, '', '');

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'content_type' => $contentType ?: 'application/octet-stream',
                'body' => substr($response, $headerSize)
            ];
        }
        return null;
    }

    /**
     * Xóa file khỏi MinIO
     */
    public function deleteObject(string $objectKey): bool {
        return $this->sendRequest('DELETE', $objectKey, '', '');
    }

    private function sendRequest(string $method, string $objectKey, string $payload, string $contentType): bool {
        $ch = curl_init();
        $url = "http://{$this->endpoint}/{$this->bucket}/{$objectKey}";
        $headers = $this->createSignedHeaders($method, $objectKey, $payload, $contentType);

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode >= 200 && $httpCode < 300);
    }

    /**
     * Ký xác thực chuẩn AWS Signature Version 4
     */
    private function createSignedHeaders(string $method, string $objectKey, string $payload, string $contentType): array {
        $host = $this->endpoint;
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $payload);

        $headers = [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $amzDate
        ];
        if (!empty($contentType)) {
            $headers['content-type'] = $contentType;
        }
        ksort($headers);

        $canonicalHeaders = "";
        $signedHeadersArr = [];
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= strtolower($k) . ":" . trim($v) . "\n";
            $signedHeadersArr[] = strtolower($k);
        }
        $signedHeaders = implode(';', $signedHeadersArr);

        $canonicalUri = '/' . $this->bucket . '/' . str_replace('%2F', '/', rawurlencode($objectKey));
        $canonicalRequest = "{$method}\n{$canonicalUri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        $algorithm = 'AWS4-HMAC-SHA256';
        $scope = "{$dateStamp}/{$this->region}/s3/aws4_request";
        $stringToSign = "{$algorithm}\n{$amzDate}\n{$scope}\n" . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authHeader = "{$algorithm} Credential={$this->accessKey}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $out = [
            "Authorization: {$authHeader}",
            "x-amz-date: {$amzDate}",
            "x-amz-content-sha256: {$payloadHash}"
        ];
        if (!empty($contentType)) {
            $out[] = "Content-Type: {$contentType}";
        }
        return $out;
    }
}