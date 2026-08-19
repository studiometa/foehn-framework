# `#[AsAbility]` — a theme an agent can operate

Not yet approved. Specced because it came out of the same review and is the one item with no equivalent in any comparable framework.

| Property             | Decision                                                                                 |
| -------------------- | ---------------------------------------------------------------------------------------- |
| Attribute            | `#[AsAbility]` → `wp_register_ability()`                                                 |
| WordPress            | 6.9 (December 2025) as a plugin, core in 7.0 (May 2026)                                  |
| Agent access         | Through `WordPress/mcp-adapter`, which Føhn does not require and does not wrap           |
| The work             | Deriving JSON Schema from typed DTO constructors. Everything else is a normal discovery. |
| Where the code lives | `packages/foehn/src/Abilities/`                                                          |

## 1. What an ability is

```php
wp_register_ability('namespace/ability-name', [
    'label'               => …,
    'description'         => …,
    'input_schema'        => …,   // JSON Schema
    'output_schema'       => …,   // JSON Schema
    'permission_callback' => …,
    'execute_callback'    => …,
    'meta'                => ['mcp' => ['public' => true]],
]);
```

A named, typed, permission-checked unit of site functionality. With the MCP adapter installed it becomes a tool an AI agent can discover and invoke; without it, it is still a well-described callable that REST and WP-CLI can reach.

The important property for Føhn: that is a discovery item with extra steps. One class, one job, a capability, typed input and output.

## 2. Shape

```php
#[AsAbility(
    name: 'theme/summarise-post',
    label: 'Summarise a post',
    description: 'Returns a short plain-text summary of a published post.',
    capability: 'edit_posts',
    mcp: true,
)]
final readonly class SummarisePost implements AbilityInterface
{
    public function __invoke(SummarisePostInput $input): SummarisePostOutput
    {
        …
    }
}

final readonly class SummarisePostInput
{
    public function __construct(
        /** The post to summarise. */
        public int $postId,
        /** Maximum length in words. */
        public int $maxWords = 60,
    ) {}
}
```

| Argument      | Default        | Notes                                                                  |
| ------------- | -------------- | ---------------------------------------------------------------------- |
| `name`        | _required_     | `namespace/name`. WordPress requires the slash.                        |
| `label`       | _required_     | Human-readable, shown to agents and in listings.                       |
| `description` | `''`           | What an agent reads to decide whether to call it. Treat it as the API. |
| `capability`  | `'edit_posts'` | Becomes `permission_callback`. No default of "public".                 |
| `mcp`         | `false`        | `true` sets `meta.mcp.public`, exposing it to the default MCP server.  |

`AbilityDiscovery` registers on `init` (`Main` phase), skipping with a debug notice when `wp_register_ability()` does not exist. `execute_callback` and `permission_callback` are built at apply time from the container-resolved instance — never stored in the item, per the no-closures rule.

## 3. Schemas from constructors

Hand-written JSON Schema is the worst part of the native API, and the part Føhn can delete. `AttributeCodec` already reflects promoted constructors to serialise attributes; the same reflection produces a schema.

| PHP                              | JSON Schema                                              |
| -------------------------------- | -------------------------------------------------------- |
| `string`                         | `{"type": "string"}`                                     |
| `int` / `float`                  | `{"type": "integer"}` / `{"type": "number"}`             |
| `bool`                           | `{"type": "boolean"}`                                    |
| `?T`                             | `{"type": ["…", "null"]}`                                |
| `T` with a default               | omitted from `required`, `default` emitted               |
| `list<string>` (docblock)        | `{"type": "array", "items": {"type": "string"}}`         |
| backed `enum`                    | `{"type": "string", "enum": [...]}` from the case values |
| nested DTO                       | `{"type": "object", …}`, recursively                     |
| docblock summary on the property | `description`                                            |

Two things this cannot do, and must therefore reject loudly rather than guess:

- **Untyped or `mixed` parameters.** No schema exists for them. Throw at discovery time with the class and parameter named.
- **Un-annotated `array`.** `array` alone says nothing an agent can use. Require a `list<T>` or `array<string, T>` docblock, or refuse.

Failing at discovery time is the right trade: an ability with a vague schema is worse than a missing one, because an agent will call it and misuse it.

## 4. What Føhn should not build

**Not an MCP server.** `WordPress/mcp-adapter` is that, it is maintained by the WordPress project, and abilities are the seam between the two. Føhn registers abilities and stops.

**Not a wrapper around the AI Client.** WordPress 7.0 ships `wp_ai_client_prompt()`, a fluent `PromptBuilder`, a Connectors screen for credentials and a `prompt_ai` capability. It is already provider-agnostic. Wrapping it would be an abstraction over an abstraction.

What is worth adding, because it is the failure mode a template invites, is a small `AiConfig` and one service:

- **Cache by prompt hash** through the existing `CacheInterface`. A prompt is slow, paid and non-deterministic; issuing the same one per request is the mistake to make structurally difficult.
- **Capability default of `prompt_ai`**, not "whatever the caller checked".
- **Twig may not prompt.** The default answer for "can a template call a model" is no. If a project wants it, it opts in, and then the caching above is what stops a listing page from making twenty calls.

That is a config object and a decorator, not a new API surface.

## 5. Why this is the differentiating item

Everything else on the roadmap makes Føhn a better WordPress framework. This makes a Føhn theme _addressable_: a site's own domain operations — summarise, reprice, rebuild a menu, validate a booking — become typed, permission-checked tools an agent can enumerate and call, with the schema derived from the code rather than maintained beside it.

Acorn does not have this. Nor does any theme framework, because it landed in WordPress four months ago. The attribute is small; the position is not.

## 6. Estimate and risks

| Phase                                                       | Estimate |
| ----------------------------------------------------------- | -------- |
| Attribute, interface, `AbilityDiscovery`, capability wiring | 1–2 days |
| Schema reflection with the rejection rules and its tests    | 2–3 days |
| `AiConfig`, prompt caching, the Twig decision, guide        | 1–2 days |

- **Schema reflection is the whole risk.** Enums, nested DTOs and arrays-of-DTOs each need a case and a test. Budget for the reflection, not the registration.
- **`wp_register_ability()` is young.** Signature and `meta` conventions may still move between 7.0 and 7.1. Keep the mapping in one class so a change is one edit, and pin the behaviour with tests against a stubbed function rather than against core.
- **Descriptions become an interface.** An agent chooses a tool by reading `description`. A vague one is a bug that shows up as an agent doing the wrong thing, which is a hard failure to debug and a new kind of review comment.
- **Exposing abilities is exposing capability.** `mcp: true` on an ability whose `capability` is too broad hands an agent more than intended. The guide leads with the capability argument, not with `mcp`.
