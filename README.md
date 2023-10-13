# Robo Backups

Utility for handling backups for a self-hosted website.

## TODO

- [ ] When Python is wrong version for AWS CLI v2
   - Example: Install AWS CLI v1 w/ pythong 3.7 - [reference](https://docs.aws.amazon.com/cli/v1/userguide/install-linux.html#install-linux-bundled)
      ```
      curl "https://s3.amazonaws.com/aws-cli/awscli-bundle.zip" -o "awscli-bundle.zip"
      unzip awscli-bundle.zip
      python3.7 ./awscli-bundle/install -b ~/bin/aws
      ```
- [ ] Document how to setup from start to finish
- [ ] Maybe a "verify" step after upload. Checksum style
- [ ] Backups retention policy

## Requirements

* S3 bucket w/ API credentials
* Server access
* Composer

## Commands

| Command                   | Description                                                         |
|---------------------------|---------------------------------------------------------------------|
| `robo config:validate`    | Simple robo.yml validation. Loads config & attempts S3 connection.  |
| `robo backup:database`    | Backup website database according to robo.yml config.               |
| `robo restore:database`   | Restore latest website database according to robo.yml config.       |
| `robo backup:files`       | Backup website non-code upload files.                               |
| `robo backup:code`        | Backup website code without upload files.                           |
| `robo backup:files-sync`  | Sync the files_root to S3 into a virtual folder named `files_sync`. |
| `robo restore:files-sync` | Sync the files_root from S3 to the `backups.files_root`.            |


## Examples

| File                                                                              | Description                              |
|-----------------------------------------------------------------------------------|------------------------------------------|
| [`robo.example-db-dump.yml`](examples/robo.example-db-dump.yml)                   | Database dump using mysqldump            |
| [`robo.example-credentials-file.yml`](examples/robo.example-credentials-file.yml) | Database dump using credentials file     |
| [`robo.example-wordpress.yml`](examples/robo.example-wordpress.yml)               | Database dump using WP CLI for WordPress |
| [`robo.example-drupal.yml`](examples/robo.example-drupal.yml)                     | Database dump using Drush for Drupal     |

## Setup

* Create S3 bucket and IAM user (see below).
* Get this software on the server. Somewhere outside of the docroot.
* Copy appropriate `examples/robo.example-*.yml` file to the root of this software on the server.
* Complete configuration file w/ S3 details and paths to app, code, & files.
* Github Actions setup (see below).

### S3 Setup

1. Create a new S3 bucket named `backups-[WEBSITE]`.
    * Region: Whatever you choose, note it.
    * ACLs disabled (recommended)
    * Block all public access
2. Create a new IAM user named `S3-backups-[WEBSITE]`. 
    * Access key - Programmatic access (does not need Login/Console access)
    * Add to "S3-backups" group
    * Download CSV of credentials and save them somewhere safe
3. Create new Access Policy (see below) to the new IAM user
    * Assign the policy to the new user (Permissions)

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

See the entry for the [`robo-scheduled-backups` workflow](https://github.com/daggerhartlab/devops/blob/main/github/workflows/robo-scheduled-backups.md) in our devops repo.
