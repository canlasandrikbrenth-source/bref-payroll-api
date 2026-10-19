<?php

namespace App\Services;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class S3Service
{
    private S3Client $s3Client;
    private string $bucket;
    private string $region;

    public function __construct(?string $bucket = null, ?string $region = null)
    {
        $envRegion = getenv('AWS_REGION') ?: getenv('AWS_DEFAULT_REGION');
        $this->region = $region ?: ($envRegion ?: 'ap-southeast-2');

        $envBucket = getenv('S3_BUCKET_NAME');
        $this->bucket = $bucket ?: ($envBucket ?: 'canlas-bref-payroll-payslips');

        $clientConfig = [
            'region'  => $this->region,
            'version' => 'latest',
        ];

        // If local access key credentials exist in env, use them; on Lambda, execution role is used automatically
        $accessKey = getenv('AWS_ACCESS_KEY_ID') ?: null;
        $secretKey = getenv('AWS_SECRET_ACCESS_KEY') ?: null;
        if ($accessKey && $secretKey) {
            $clientConfig['credentials'] = [
                'key'    => $accessKey,
                'secret' => $secretKey,
            ];
            $sessionToken = getenv('AWS_SESSION_TOKEN') ?: null;
            if ($sessionToken) {
                $clientConfig['credentials']['token'] = $sessionToken;
            }
        }

        $this->s3Client = new S3Client($clientConfig);
    }

    /**
     * Upload a payslip PDF to S3
     * 
     * @param int $payrollId
     * @param string $pdfContent
     * @return array [ 'key' => string, 'url' => string ]
     */
    public function uploadPayslipPdf(int $payrollId, string $pdfContent): array
    {
        $key = "payslips/{$payrollId}.pdf";

        try {
            $result = $this->s3Client->putObject([
                'Bucket'      => $this->bucket,
                'Key'         => $key,
                'Body'        => $pdfContent,
                'ContentType' => 'application/pdf',
            ]);

            // Construct standard S3 Object URL
            $url = $result['ObjectURL'] ?? "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$key}";

            return [
                'key' => $key,
                'url' => $url,
            ];
        } catch (AwsException $e) {
            throw new \RuntimeException("Failed to upload payslip to S3: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public function getBucketName(): string
    {
        return $this->bucket;
    }
}
