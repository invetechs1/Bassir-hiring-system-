# Deployment

The app runs on the server as a Docker Compose stack (`app` + `db`) at
`/root/bassir-laravel-shared-hosting`, published on port **8083**
(`http://13.140.138.252:8083`). Deployment ships a pre-built Docker image
rather than rebuilding source on the server.

- Image name: `bassir-laravel-shared-hosting-app:latest`
- Compose project directory on server: `/root/bassir-laravel-shared-hosting`
- Server-side files: `docker-compose.yml`, `.env` (production secrets, not in
  git), persistent volumes `db_data` and `storage_data`

## One command

From the project root, with Docker running locally and SSH access to the
server:

```bash
./scripts/build-and-ship.sh
```

This does all five steps below in order. Everything past this point explains
what that script does, so you can run the steps by hand if needed.

## Step by step

### 1. Remove the old local image

```bash
docker rmi bassir-laravel-shared-hosting-app:latest
```

Clears the local tag so the next build can't be mistaken for a stale image.
Safe to ignore if it doesn't exist yet.

### 2. Build the image

```bash
docker build -t bassir-laravel-shared-hosting-app:latest .
```

Uses the `Dockerfile` in the project root (PHP 8.4 + Apache, extensions,
`composer install --no-dev --optimize-autoloader`).

### 3. Save the image to a tar

```bash
docker save -o bassir-laravel-shared-hosting-app.tar bassir-laravel-shared-hosting-app:latest
```

### 4. Send the tar to the server

```bash
scp bassir-laravel-shared-hosting-app.tar root@13.140.138.252:/root/bassir-laravel-shared-hosting/
```

### 5. Run the server-side script

```bash
ssh root@13.140.138.252 "cd /root/bassir-laravel-shared-hosting && bash server-deploy-image.sh"
```

`scripts/server-deploy-image.sh` (also kept in this repo, and copied onto the
server at `/root/bassir-laravel-shared-hosting/server-deploy-image.sh`) does:

```bash
docker load -i bassir-laravel-shared-hosting-app.tar   # import the shipped image
docker compose up -d app                                # recreate only the app container
docker image prune -f                                    # drop the now-dangling old image
rm -f bassir-laravel-shared-hosting-app.tar               # clean up the tar
```

`docker compose up -d app` only touches the `app` service — the `db`
container and its `db_data` / `storage_data` volumes are never recreated or
wiped by this process.

## Verifying a deploy

```bash
curl -I http://13.140.138.252:8083/login
ssh root@13.140.138.252 "docker logs --tail 50 bassir-laravel-shared-hosting-app-1"
ssh root@13.140.138.252 "docker exec bassir-laravel-shared-hosting-app-1 php artisan migrate --force"
```

The `migrate --force` call is idempotent — safe to run after every deploy in
case new migrations shipped with the image; it reports "Nothing to migrate"
if the schema is already current.

## Rollback

Before each deploy, back up the current server-side source directory
(excluding `vendor` and `storage`, which are rebuilt/persisted separately):

```bash
ssh root@13.140.138.252 "mkdir -p /root/backups && tar czf /root/backups/pre-deploy-\$(date +%Y%m%d%H%M%S).tar.gz -C /root/bassir-laravel-shared-hosting --exclude=vendor --exclude=storage ."
```

To roll back the running container to the previous image, retag it back to
`:latest` and recreate (Docker keeps the old image around until
`docker image prune` removes it, so this only works if you haven't pruned
yet):

```bash
docker tag bassir-laravel-shared-hosting-app:<previous-image-id> bassir-laravel-shared-hosting-app:latest
docker compose up -d app
```

## Notes

- The server's `.env` holds production DB credentials and app secrets — it
  is never touched by this process and is not tracked in git.
- `Dockerfile`, `docker-compose.yml`, and `docker/apache-vhost.conf` live in
  this repo so the image can be built from a clean checkout; they were
  originally added directly on the server and pulled back into version
  control here.
- An alternative technique (rebuild from source directly on the server via
  `docker compose build`) also works against the same `docker-compose.yml`
  and was used for earlier deploys, but the image-ship workflow above is the
  standard path going forward.
