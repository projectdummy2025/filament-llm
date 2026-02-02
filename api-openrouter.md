# Contoh Penggunaan API OpenRouter

Berikut adalah kumpulan contoh kode untuk berinteraksi dengan API OpenRouter menggunakan berbagai bahasa dan library.

---

### 1. cURL

Contoh dasar menggunakan `curl` di terminal.

```bash
curl https://openrouter.ai/api/v1/chat/completions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $OPENROUTER_API_KEY" \
  -d '{
    "model": "qwen/qwen3-next-80b-a3b-instruct:free",
    "messages": [
      {
        "role": "user",
        "content": "What is the meaning of life?"
      }
    ]
  }'
```

---

### 2. Python (`requests`)

Implementasi standar menggunakan library `requests`.

```python
import requests
import json

response = requests.post(
  url="https://openrouter.ai/api/v1/chat/completions",
  headers={
    "Authorization": "Bearer <OPENROUTER_API_KEY>",
    "HTTP-Referer": "<YOUR_SITE_URL>", # Opsional
    "X-Title": "<YOUR_SITE_NAME>", # Opsional
  },
  data=json.dumps({
    "model": "qwen/qwen3-next-80b-a3b-instruct:free",
    "messages": [
      { "role": "user", "content": "What is the meaning of life?" }
    ]
  })
)

print(response.json())
```

---

### 3. Python (`openai-python`)

Menggunakan library resmi dari OpenAI yang kompatibel.

```python
from openai import OpenAI

client = OpenAI(
  base_url="https://openrouter.ai/api/v1",
  api_key="<OPENROUTER_API_KEY>",
)

completion = client.chat.completions.create(
  extra_headers={
    "HTTP-Referer": "<YOUR_SITE_URL>", # Opsional
    "X-Title": "<YOUR_SITE_NAME>", # Opsional
  },
  model="qwen/qwen3-next-80b-a3b-instruct:free",
  messages=[
    {
      "role": "user",
      "content": "What is the meaning of life?",
    },
  ],
)

print(completion.choices[0].message.content)
```

---

### 4. TypeScript/JavaScript (`fetch`)

Implementasi menggunakan `fetch` API bawaan.

```typescript
async function getCompletion() {
  const response = await fetch("https://openrouter.ai/api/v1/chat/completions", {
    method: "POST",
    headers: {
      "Authorization": "Bearer <OPENROUTER_API_KEY>",
      "Content-Type": "application/json",
      "HTTP-Referer": "<YOUR_SITE_URL>", // Opsional
      "X-Title": "<YOUR_SITE_NAME>", // Opsional
    },
    body: JSON.stringify({
      "model": "qwen/qwen3-next-80b-a3b-instruct:free",
      "messages": [
        { "role": "user", "content": "What is the meaning of life?" }
      ]
    })
  });

  const data = await response.json();
  console.log(data.choices[0].message.content);
}

getCompletion();
```

---

### 5. TypeScript (`openai-typescript`)

Menggunakan library `openai` versi TypeScript.

```typescript
import OpenAI from 'openai';

const openai = new OpenAI({
  baseURL: "https://openrouter.ai/api/v1",
  apiKey: "<OPENROUTER_API_KEY>",
  defaultHeaders: {
    "HTTP-Referer": "<YOUR_SITE_URL>", // Opsional
    "X-Title": "<YOUR_SITE_NAME>", // Opsional
  },
});

async function main() {
  const completion = await openai.chat.completions.create({
    model: "qwen/qwen3-next-80b-a3b-instruct:free",
    messages: [
      { "role": "user", "content": "What is the meaning of life?" }
    ],
  });

  console.log(completion.choices[0].message);
}

main();
```

---

### 6. TypeScript (`@openrouter/sdk`)

Menggunakan SDK resmi dari OpenRouter.

```typescript
import { OpenRouter } from "@openrouter/sdk";

const openrouter = new OpenRouter({
  apiKey: "<OPENROUTER_API_KEY>"
});

async function main() {
    const stream = await openrouter.chat.completions.create({
        model: "qwen/qwen3-next-80b-a3b-instruct:free",
        messages: [
            { role: "user", content: "What is the meaning of life?" },
        ],
        stream: true,
    });

    for await (const chunk of stream) {
        process.stdout.write(chunk.choices[0]?.delta?.content || "");
    }
}

main();
```