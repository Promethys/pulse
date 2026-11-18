AI requests use your provider account. Some providers have free tiers, and yours may charge beyond them or require paid access for your chosen model.

## How much a day uses

A day makes two requests: one for news selection and one for learning. A typical day, with about 12 sources and 20 items, uses about 10,000 input tokens and 2,500 output tokens, so about 12,000 in total.

Input grows with the number of sources and headlines, because Pulse sends them to the model for selection. Output is limited by **Max items per day**, so more items means more output.

Approximate cost of a typical day on Gemini's paid tier, at **prices as of November 2026**. Gemini's free tier costs nothing.

| Model                 | Per day | Per month |
| --------------------- | ------- | --------- |
| 3.8 Flash, 2026 price | $0.017  | $0.50     |
| 3.8 Flash, 2027 price | $0.034  | $1        |
| 3.1 Flash-Lite        | $0.006  | $0.20     |

Check [Google's pricing page](https://aistudio.google.com/docs/pricing) for current prices. Prices change, and other providers price differently, so check your provider's page before adding a key.

On Gemini's free tier, Google may use your requests, which include your About you text and source excerpts, to improve its products. Choose a paid tier if that matters to you.

## Check with your provider

Review pricing, available models and your balance on your provider's own site. An API key does not by itself guarantee free usage or access to every model.

The links in [Get a free key](/docs/ai-keys/get-a-free-key) take you to the providers' key pages. Check their current terms before saving a key.

## What uses your key

News selection, learning generation and **Test the key** make provider requests. Saved-key **Test** also makes a real request and may incur a charge.

Using a fallback chain can make requests to another provider when the first is unavailable. Each provider applies its own billing terms.

## Control what runs

In **Settings → Digest**, you can turn off News or Learning and change **Max items per day**. The item setting limits news stories; it is not a spending cap.

Use your provider's billing controls to manage spending. Deleting your last personal key pauses scheduled digests unless your account has instance AI access.
