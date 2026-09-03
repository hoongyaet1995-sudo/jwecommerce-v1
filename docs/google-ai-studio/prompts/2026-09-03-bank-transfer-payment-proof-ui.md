# Google AI Studio Prompt: Bank Transfer Payment Proof Closure

Use the latest accepted JW Studio Ecommerce source project as the base. Do not redesign the pages. Preserve every existing accepted region, route, DOM hierarchy, Tailwind visual language, dark/light theme, navigation shell, typography, spacing, cards, product content, and responsive behavior that is outside the exact regions requested below.

## Goal

Design the customer-facing UI/UX for submitting and resubmitting Bank In / DuitNow payment proof. Laravel will own authorization, validation, persistence, private file storage, and payment status. Your task is presentation and interaction source only.

The order number is generated only after checkout, so do not force customers to provide a receipt before an order exists. The receipt is mandatory before payment can be verified or marked paid.

## Existing visual source to reuse

Use the existing Checkout `Payment Receipt / Slip` upload component as the design-family source:

- amber accent and upload-cloud icon;
- dashed empty drop zone;
- selected-file row with filename, file icon, readiness status, and remove control;
- PDF/JPG/PNG, maximum 10 MB;
- existing rounded corners, borders, dark-mode colors, typography scale, spacing, hover and focus behavior.

Improve and extend this exact visual family. Do not substitute a third-party component library and do not create a visually unrelated uploader.

## Region A: Checkout copy adjustment

Keep the existing upload component in the same position and structure. Replace the misleading `(optional)` wording with customer-friendly copy explaining:

- `Upload now if you have completed the transfer`
- `You can also submit proof after your order number is created`

Do not alter the surrounding bank-account selector or order-summary layout.

## Region B: `/order/success`

Insert one compact Bank In / DuitNow payment-proof panel within the existing order-confirmation content, close to the order number and payment status. Do not create a new page.

Required states:

1. **Missing / Action Required**
   - Clear heading: `Upload Payment Proof`
   - Explain that the order is reserved but payment cannot be verified until a receipt is submitted.
   - Show the generated Order Number as the transfer reference.
   - Reuse the existing upload drop zone.
   - Primary action submits the selected file.

2. **Selected / Uploading**
   - Show filename and selected-file presentation from Checkout.
   - Upload button has disabled/loading state and animated progress indicator.
   - Prevent double submission.

3. **Uploaded / Awaiting Verification**
   - Emerald confirmation treatment consistent with the existing storefront.
   - Show filename and uploaded time.
   - Copy: `Payment proof submitted — awaiting verification`.
   - Allow replacement only while Laravel says `canReplace=true`.

4. **Rejected**
   - Restrained red/error treatment using existing storefront error styling.
   - Show the safe rejection reason.
   - Primary action: `Upload New Proof`.

5. **Verified / Locked**
   - Emerald verified state.
   - No upload or replacement action.

6. **Validation or upload failure**
   - Inline error close to the upload control.
   - Preserve the currently selected file where safe.
   - Do not use a browser alert.

## Region C: `/account#purchase_history`

Inside the existing expanded order card, add a compact payment-proof status/action block only for Bank In / DuitNow orders. It must look native to the current Purchase History card and must not change the outer order-card hierarchy.

Use the same six states and interaction language as Region B. Keep the information density appropriate for an account-history row:

- missing: `Upload Payment Proof`;
- uploaded: `Pending Verification` plus filename/time;
- rejected: safe reason plus `Resubmit Proof`;
- verified: `Payment Verified`, locked;
- uploading and error states inline.

Do not show this block for card, PayPal, Billplz, Stripe, or COD orders.

## Responsive and accessibility requirements

- Supply source for desktop and mobile layouts.
- No horizontal overflow at 320 px width.
- Buttons and drop zones must be keyboard accessible.
- Include visible focus states, meaningful labels, and screen-reader status text.
- Drag-and-drop is enhancement only; file-picker interaction must always work.
- Long filenames truncate without pushing actions off-screen.
- Do not hide required payment status or upload actions on mobile.

## Data and event placeholders

Use a small typed view model and callbacks so Laravel can bind without changing your DOM:

- `paymentMethod`
- `proofStatus`
- `originalFileName`
- `uploadedAt`
- `rejectionReason`
- `canUpload`
- `canReplace`
- `isUploading`
- `validationError`
- `onSelectFile`
- `onSubmitProof`
- `onRemoveSelectedFile`

Use mock values only to demonstrate every visual state. Clearly keep mocks isolated from the component and do not invent APIs, database behavior, Admin behavior, or payment transitions.

## Deliverable

Return the complete updated Google AI Studio ZIP based on the latest accepted project, including:

- exact component file paths for Regions A, B, and C;
- all direct and transitive source dependencies used by those regions;
- desktop and mobile previews for all six states;
- no changes to unrelated accepted regions.

Do not provide screenshots only. The React/HTML/Tailwind source is required for a source-faithful Laravel integration.
