# Storefront chat PDF removal

User approved removal and synchronization. The headset-looking icon was actually labelled Attach PDF. Remove its button/input/handlers and reject storefront document uploads server-side before conversation/storage creation. Retain image upload, existing private document access, staff/social attachments, and payment-proof upload.

Verification: intentional red with a valid PDF returned 201 before guard, then 129 document/image/chat regression tests passed (892 assertions, 26.10s). Legacy-document tests now seed historical documents through the existing service rather than the removed endpoint. Local rendered homepage has no PDF button, retains image attachment, and extracted chat JavaScript passes node --check. No paid API calls or data changes.

Release scope: controller, shared storefront chat component, document regression test and this record only. No migrations, assets build or environment edits.
