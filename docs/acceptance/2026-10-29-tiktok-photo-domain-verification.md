# TikTok photo URL-prefix verification

- Requested scope: verify https://ecom.jwaistudio.online/marketing/post-delivery/ for existing TikTok developer application 7606162435250227256. Existing www.jwaistudio.online verification and OAuth callbacks remain unchanged.
- Root cause: production verified-prefix config was empty; TikTok URL properties only showed www.jwaistudio.online (Domain). Actual photo delivery URLs use ecom.jwaistudio.online.
- TikTok supplied a public signature text file for the exact URL prefix. Preserve its exact bytes, serve as a static public file, and enable the local readiness marker only after provider verification succeeds.
- No post publishing, account reauthorization, messaging, or paid API calls in this task.
- Initial verification: signature downloaded via normal developer UI; local file is 68 bytes. Local/production HTTP bytes must match SHA256 before provider Verify.
- Existing delivery URLs remain signed; static proof file does not bypass media access controls.
