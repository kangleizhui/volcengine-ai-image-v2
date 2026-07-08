# 火山AI生图 API 文档

## Base URL

```
http://www.volcengine.com:8080
```

所有接口返回 JSON，编码 UTF-8。

---

## 1️⃣ 图片生成

生成 AI 图片，支持文生图。

```
GET /api/generate.php?key={密钥}&prompt={描述}&size={尺寸}&n={张数}
```

### 参数

| 参数 | 必填 | 说明 |
|------|------|------|
| `key` | 是 | API密钥 |
| `prompt` | 是 | 图片描述文字 |
| `size` | 否 | 尺寸。默认 `1920x1920`（Seedream模型要求最小1920x1920）。可选：`1920x1920`、`1536x1536`、`1024x1024`、`768x768`、`512x512`、`1024x768`、`768x1024`、`1920x1080`、`1080x1920` |
| `n` | 否 | 生成张数，1-4，默认1 |
| `model` | 否 | 模型ID。不传则使用管理员后台设置的默认模型。可用模型列表见下方「模型查询」 |

### 响应

```json
{
  "code": 200,
  "msg": "生成成功",
  "data": {
    "images": [
      {
        "url": "https://...generated.jpeg",
        "revised_prompt": "优化后的描述"
      }
    ],
    "prompt": "用户原始描述",
    "size": "1920x1920",
    "n": 1,
    "model": "doubao-seedream-5-0-260128",
    "key_info": {
      "is_test": true,
      "type": "count",
      "remaining": null,
      "daily_remaining": 9,
      "expires_at": null
    }
  }
}
```

图片 URL 在 `data.images[].url`。`key_info` 包含密钥状态信息。

### 错误码

| HTTP | 说明 |
|------|------|
| 400 | 缺少参数 |
| 403 | 密钥无效/过期/禁用 |
| 429 | 测试密钥每日限10次 |
| 500 | 服务端错误 |

---

## 2️⃣ 密钥查询

```
GET /api/key_info.php?key={密钥}
```

不消耗次数，返回密钥状态。

```json
{
  "code": 200,
  "msg": "ok",
  "data": {
    "is_test": true,
    "type": "count",
    "is_active": true,
    "remaining": 8,
    "daily_remaining": 9,
    "expires_at": null
  }
}
```

---

## 3️⃣ 创建订单 / 查询支付

### 创建订单

```
POST /pay/create.php
Content-Type: application/json

{"id": "count_50", "name": "按次 50次", "type": "count", "count": 50, "price": 9.90}
```

响应含 `order_id` 和 `qr_code`（支付宝付款码链接）。

> ⚠️ 如果创建订单失败，提示用户：当前API项目还未配置支付通道，请联系技能开发者配置。

### 查询订单

```
GET /pay/query.php?order_id={订单号}
```

`status=paid` 时返回 `api_key`。

---

## 4️⃣ 模型管理（管理员）

### 查询可用模型

```
GET /api/volc_models.php
```

返回按 `domain` 分组的模型列表，包括：
- 🖼️ **生图模型**（`ImageGeneration`）— 推荐选择
- 👁️ **多模态理解**（`VLM`）— 看图但不能生图
- 💬 **文本对话**（`LLM`）— 纯文本

需先在后台配置火山引擎 API Token 才能查询。
