# Doctor and product readiness

Doctor's `--release` gate verifies technical and content integrity. It is not a
product usability or public-launch approval. Run the independent product audit
from the repository root:

```sh
php tools/product-readiness.php
php tools/product-readiness.php --json
```

The product report inventories actual routes and project files, reads the
evidence register in `config/product-readiness.json`, and fails closed while
in-scope behavior, mandatory evidence, or a release-blocking missing capability
remains unresolved. It does not infer a technical release PASS; run
`php tools/doctor.php --release --json` separately. Product audit output is
CLI-only. Do not expose it, Doctor, or Developer tooling as a public diagnostic
endpoint.

Keep defect entries separate from missing capabilities and intentionally
deferred scope. Record reproduction and test evidence before changing statuses.
Real browser/device acceptance, site-specific privacy terms, and actual host
behavior require external evidence and cannot be marked PASS by local tests.
