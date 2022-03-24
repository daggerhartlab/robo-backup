# Robo Backups

Utility for handling backups for a self-hosted website.

## TODO

- [ ] Delete local files after transfer to S3
- [ ] Example github actions
- [ ] Document how to setup from start to finish
- [ ] Maybe a "verify" step after upload. Checksum style

## Requirements

* S3 bucket w/ API credentials
* Server access

## Commands

| Command | Description |
|---|---|
|`robo backup:database`| Backup website database according to robo.yml config. |
|`robo backup:files`| Backup website non-code upload files. |
|`robo backup:code`| Backup website code without upload files. |
|`robo backup:files-sync`| Sync the files_root to S3 into a virtual folder named `files_sync`. |

## Setup

* Create S3 bucket and IAM user (see below).
* Get this software on the server. Likely global install for the user.
* Copy appropriate `examples/robo.example-*.yml` file to `~/.robo/robo.yml`
* Complete configuration file w/ S3 details and paths to app, code, & files.
* Github Actions setup (see below).

### S3 Setup

1. Create a new S3 bucket named `backups-[WEBSITE]`.
1. Create a new IAM user named `S3-backups-[WEBSITE]`. 
    * Add to "S3-backups" group
    * API access, does not need Login/Console access.
1. Save the credentials to LastPass as part of the Note named "AWS S3 Client Backups Credentials"
1. Attach a new Access Policy (see below) to the new IAM user.

#### Access Policy

* `backups-[WEBSITE]` - Replace this with the S3 bucket name to limit access appropriately.

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "VisualEditor0",
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:GetObjectAcl",
                "s3:GetObject",
                "s3:DeleteObjectVersion",
                "s3:ListBucketVersions",
                "s3:ListBucket",
                "s3:DeleteObject",
                "s3:PutObjectAcl",
                "s3:GetObjectVersion"
            ],
            "Resource": [
                "arn:aws:s3:::backups-[WEBSITE]/*",
                "arn:aws:s3:::backups-[WEBSITE]"
            ]
        }
    ]
}
```

### Github Actions Setup

@todo 