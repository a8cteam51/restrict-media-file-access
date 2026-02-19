# .agents — Restrict Media File Access Plugin Agent Skills

This folder contains plugin-specific skills for AI coding assistants working on the Restrict Media File Access WordPress plugin.

## Structure

```
.agents/
├── README.md
└── skills/
    ├── rmfa-architecture/SKILL.md       ← Plugin internals, service pattern, REST API, cron, settings
    ├── rmfa-file-protection/SKILL.md    ← File protection pipeline, URL rewriting, access control, serving
    └── rmfa-testing/SKILL.md            ← Test frameworks, commands, writing new tests
```

## Which Skill to Use

| Task | Skill |
|------|-------|
| Working on plugin PHP (services, REST API, cron, settings) | `rmfa-architecture` |
| File protection, URL rewriting, access control, or file serving | `rmfa-file-protection` |
| Writing or running tests | `rmfa-testing` |

## Recommended: WordPress Agent-Skills

This plugin also benefits from the community [WordPress/agent-skills](https://github.com/WordPress/agent-skills). These are **not bundled** — install them globally to keep them up-to-date:

```bash
# Clone and build
git clone https://github.com/WordPress/agent-skills.git
cd agent-skills
node shared/scripts/skillpack-build.mjs --clean

# Install globally for Cursor
node shared/scripts/skillpack-install.mjs --targets=cursor-global

# Or install globally for Claude Code
node shared/scripts/skillpack-install.mjs --global
```

The following skills are most relevant to this project:

| Skill | Relevance |
|-------|-----------|
| `wp-plugin-development` | General plugin architecture, hooks, settings, security. |
| `wp-rest-api` | REST API conventions for `restrict-media-file-access/v1` endpoints. |
| `wp-performance` | Profiling, caching, database optimization. |
| `wp-wpcli-and-ops` | WP-CLI operations and automation. |
| `wp-playground` | Local development with wp-env. |
| `wp-phpstan` | PHPStan static analysis. |
| `wordpress-router` | Repo classification and workflow routing. |
| `wp-project-triage` | Project type and tooling detection. |
