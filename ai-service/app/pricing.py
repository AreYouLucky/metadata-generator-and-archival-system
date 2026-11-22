"""Estimated provider prices in USD, used for `usage_records.estimated_cost`.

From OpenAI's pricing page on 2026-11-21 (standard tier, short context). Prices
change: update this table, not the code that uses it. Unknown models get no estimate.
"""

from typing import TypedDict


class TokenPrice(TypedDict):
    input: float
    cached_input: float
    output: float


# Per 1M tokens.
TOKEN_PRICES: dict[str, TokenPrice] = {
    "gpt-6.2-astra": {"input": 8.00, "cached_input": 0.40, "output": 40.00},
    "gpt-6.2-sol": {"input": 2.00, "cached_input": 0.10, "output": 10.00},
    "gpt-6.1-terra": {"input": 1.00, "cached_input": 0.05, "output": 5.00},
    "gpt-6-luna": {"input": 0.10, "cached_input": 0.01, "output": 0.50},
    # Embeddings bill input tokens only.
    "text-embedding-3-small": {"input": 0.02, "cached_input": 0.02, "output": 0.0},
    "text-embedding-3-large": {"input": 0.13, "cached_input": 0.13, "output": 0.0},
}

# Per minute of audio. AssemblyAI from its pricing page on 2026-11-22 (per hour:
# assemblyai-stt-3-7 $0.21, universal-2 $0.15; speaker labels +$0.02).
TRANSCRIPTION_PRICES: dict[str, float] = {
    "whisper-1": 0.006,
    "local": 0.0,
    "assemblyai-stt-3-7": 0.21 / 60,
    "universal-2": 0.15 / 60,
}
ASSEMBLYAI_SPEAKER_LABELS_PER_MINUTE = 0.02 / 60


def input_cost(model: str, input_tokens: int, cached_tokens: int) -> float | None:
    price = TOKEN_PRICES.get(model)
    if price is None:
        return None
    uncached = max(input_tokens - cached_tokens, 0)
    return (uncached * price["input"] + cached_tokens * price["cached_input"]) / 1_000_000


def output_cost(model: str, output_tokens: int) -> float | None:
    price = TOKEN_PRICES.get(model)
    if price is None:
        return None
    return output_tokens * price["output"] / 1_000_000


def transcription_cost(model: str, minutes: float) -> float | None:
    price = TRANSCRIPTION_PRICES.get(model)
    return None if price is None else minutes * price
