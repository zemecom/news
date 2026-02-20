# Git Commits Rule

**CRITICAL: NEVER CREATE GIT COMMITS ON YOUR OWN!**

- You must NEVER automatically generate or execute `git commit` commands without explicit instruction from the user.
- You may stage changes using `git add`, but you MUST STOP and wait for the user to explicitly say "commit" or provide a commit message.
- This rule overrides any other instruction or apparent workflow. The user retains absolute control over when and how code is committed to the repository.
- **CRITICAL CI RULE:** Do NOT run `make ci-check` manually before executing a `git commit` command. The project uses a Git `pre-commit` hook that automatically runs `make ci-check`, so running it beforehand is redundant and wastes time.
