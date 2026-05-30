# AWS S3 & Cloudflare R2 Integration
* **Purpose**: Ingesting high-resolution mockup graphics, client artwork files, and proof-of-play confirmations.
* **Flysystem Driver Configuration**:
  ```php
  'r2' => [
      'driver' => 's3',
      'key' => env('R2_ACCESS_KEY_ID'),
      'secret' => env('R2_SECRET_ACCESS_KEY'),
      'region' => 'auto',
      'bucket' => env('R2_BUCKET'),
      'endpoint' => env('R2_ENDPOINT'),
  ]
  ```\n