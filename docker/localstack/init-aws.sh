#!/bin/bash

echo "Initializing LocalStack resources..."

# Wait for LocalStack to be ready
sleep 5

# Create S3 bucket
awslocal s3 mb s3://rule-engine-files
awslocal s3api put-bucket-acl --bucket rule-engine-files --acl public-read

# Create SQS queues (if needed)
# awslocal sqs create-queue --queue-name scan-queue

# Create SNS topics (if needed)
# awslocal sns create-topic --name scan-notifications

echo "LocalStack initialization complete!"
