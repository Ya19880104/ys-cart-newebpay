# Execution Plan

## Active Roles

- RD / integrator: owns local implementation, GitHub release, Hub registration, and final verification.
- Agents Orchestrator: used for read-only exploration and contract extraction.
- Backend Architect: YS CART gateway/callback lifecycle alignment.
- API Tester: callback crypto, route, and release-contract checks.
- Reality Checker: Hub/release/deployment constraints.

## Steps

1. Create a clean standalone plugin repo at `plugins-dev/ys-cart-newebpay`.
2. Implement bootstrap, provider registration, admin settings, and Hub client bundling.
3. Implement NewebPay MPG client crypto, query/refund API wrappers, and seven gateway variants.
4. Implement notify/return callback routes, order lookup, idempotent lifecycle updates, and logistics field persistence.
5. Add headless docs, SDK helper, skill notes, release builder, and regression contract tests.
6. Run PHP lint, regression checks, and release build.
7. Create GitHub public repo, push `main`, tag `v1.0.0`, and create a GitHub release with the ZIP asset.
8. Register or sync `ys-cart-newebpay` in YS Hub and verify public Hub endpoints.
9. Install or smoke-test on `dev-newcommerce`.
