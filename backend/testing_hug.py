
import os
from dotenv import load_dotenv
from openai import OpenAI

load_dotenv()

token = os.getenv("HUGGINGFACE_API_TOKEN")
model = os.getenv(
    "HF_LLM_MODEL",
    "meta-llama/Llama-3.2-3B-Instruct:featherless-ai"
)

if not token:
    raise ValueError("HUGGINGFACE_API_TOKEN is missing from .env")

client = OpenAI(
    base_url="https://router.huggingface.co/v1",
    api_key=token,
)

completion = client.chat.completions.create(
    model=model,
    messages=[
        {
            "role": "user",
            "content": "What are the hostel home pass rules?"
        }
    ],
    max_tokens=200,
)

print(completion.choices[0].message.content)
