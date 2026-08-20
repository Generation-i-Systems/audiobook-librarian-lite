# API client generation

Every Lite instance exposes its current contract at:

```text
https://your-lite-host/api/v1/openapi.json
```

Use that URL with OpenAPI Generator, for example:

```bash
openapi-generator-cli generate \
  -i https://your-lite-host/api/v1/openapi.json \
  -g typescript-axios \
  -o ./ablibrarian-lite-client
```

Generate from the target instance, not a historical hosted URL, so the client matches its enabled
sync, bookmark, tag, goal, friend, and achievement endpoints.
