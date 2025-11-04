# Rivne's electricity CasaOS component

1. Build container (from the root folder)
```bash
docker build -t xpert13/rivne-electricity-bot-worker:latest -f docker/worker/Dockerfile-casaos .
```

2. Push to docker hub
```bash
docker push xpert13/rivne-electricity-bot-worker:latest
```

