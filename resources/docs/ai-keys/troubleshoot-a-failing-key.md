Open **Settings → AI keys** and use **Test** beside the saved key. The result helps distinguish a provider problem from a key or model problem.

## Check the message

| Result                        | What to check                                                           |
| ----------------------------- | ----------------------------------------------------------------------- |
| Provider rejected this key    | Confirm the provider matches the key. Replace a revoked or invalid key. |
| Provider rejected the request | Check the exact model name and whether your account can use it.         |
| Rate limiting or no balance   | Check your provider's usage and billing pages, then try later.          |
| No credit left                | Review your provider balance and billing settings.                      |
| Provider overloaded           | Wait and try again.                                                     |
| Could not reach the provider  | Try again after the connection recovers.                                |

To replace a key or model, choose **Add a key** and select the same provider. See [Add and order your keys](/docs/ai-keys/add-and-order-your-keys).

## If tests are temporarily blocked

Key tests allow six requests per minute. Wait before testing again. Tests are real provider requests and may incur charges.

## If a digest still fails

Make sure News or Learning is enabled in **Settings → Digest**. Learning also requires an interest marked **Includes learning**.

When all providers are unavailable for errors that support fallback, news can use saved headlines without summaries. Learning has no equivalent fallback and can fail for a later retry. Invalid requests and missing AI configuration can fail generation instead.

Never include a full API key in a [bug report](/docs/help/contact-and-bug-reports).
