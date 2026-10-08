# Values List

Globally reusable `values_list` section, available under Benefits & Trust in Add Section. It has its own Inspector and shared Builder/saved-page renderer. No About Us or template condition.

Content uses stored `title`, optional `subtitle`/`content`, and ordered `items` with stable `id`, `title`, `description`. Visible numbers derive from order starting at 01. Add, delete, edit and reorder retain IDs and unedited fields. Three sample values are initial content, not a fixed count.

Optional `valuesShowHeading` defaults false and controls heading/introduction visibility. `valuesRowSpacing` accepts compact/normal/spacious; `valuesShowDividers` defaults true. `valuesNumberColor` and `valuesDividerColor` override theme defaults. Standard background, custom title/body/eyebrow colors, section spacing and motion remain available. The dedicated editor exposes no icon, image, badge, CTA, columns or alignment controls.

Number color and Divider color live under Brand Palette & Colors / Micro Color Controls, with a native swatch picker and synchronized Hex input matching existing Inspector styling. Layout keeps the spacing and divider toggle. Hiding dividers hides their color control but retains its stored value. No schema or renderer migration is needed.

Desktop uses number/title/description columns; below 768px container width, each row has its number beside stacked title and description. Ordered list semantics and stable IDs survive rendering and persistence.

Existing `feature_cards` stays the original icon-card authoring experience. The abandoned local `featureCardsPreset: values-list` trial remains readable through the legacy renderer/contract, with no visible preset editor. About draft29 is converted explicitly with a local backup; no published documents are migrated. No database migration, publishing, Planner change or deployment is included.
