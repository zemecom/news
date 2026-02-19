---
description: Run this checklist after completing a task to ensure quality and correctness.
---

1.  **Review against User Request**:
    - [ ] Did I solve the _actual_ problem?
    - [ ] Did I respect all constraints (e.g., specific libraries, no breaking changes)?

2.  **Code Logic & Quality**:
    - [ ] **Redundancy**: Are there any duplicate checks or logic? (e.g., `if (x) { ... } if (x) { ... }`)
    - [ ] **Simplicity**: Can this be done simpler?
    - [ ] **Styles**: Did I run `make lint`?

3.  **Dependencies & Configuration**:
    - [ ] If I added a config option, did I add the required package/extension?
        - [ ] `composer.json`?
        - [ ] `Dockerfile`? (PHP extensions)
    - [ ] If I added a service in `docker-compose`, did I update the app config to use it?

4.  **Verification**:
    - [ ] Did I run tests? `make test` or `make ci-check`.
    - [ ] Did I verify the fix manually (if possible)?

5.  **Cleanup**:
    - [ ] Remove temporary files.
    - [ ] Remove debug code (`dd`, `var_dump`).
