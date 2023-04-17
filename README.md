<p align="center">
  <img src="doc/cospirit-connect.png">
</p>

# HAL RestClient [![CircleCI](https://circleci.com/gh/cospirit/hal-rest-client.svg?style=shield&circle-token=83d86dff77250ed8812fe50f0df7ad7085e14261)](https://circleci.com/gh/cospirit/hal-rest-client)

This is an HTTP client based on guzzle to consume HAL REST API

## Development

### Requirements

Install Docker as described in the [_Docker_](https://app.gitbook.com/@cospirit-connect/s/guide-de-demarrage/installation-des-projets/prerequis/docker) section of the Start Guide.

### Installation

Check the [Start guide](https://app.gitbook.com/@cospirit-connect/s/guide-de-demarrage/) of the documentation for base initialization.

#### Initialize project

```bash
    make development@install
```

### Usage (with Docker)

Install the application :
```bash
    make development@install
```

Restart the docker compose service :
```bash
    make development@restart
```

Remove and clean docker containers :
```bash
    make development@down
```

## Tests

```bash
    make test@phpunit
```
