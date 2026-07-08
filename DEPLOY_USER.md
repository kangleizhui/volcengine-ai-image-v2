# 🚀 火山AI生图 — 从零部署到上线

> 一份写给想卖AI生图服务的人的完整部署指南。
> 该项目通过火山引擎（豆包大模型）提供 AI 生图能力，配合支付宝当面付实现自动售卖，搭配 Hermes AI Agent 实现全自动客服交付。

---

## 📦 项目概览

```
用户访问
    │
    ├── 🏠 首页           → 产品介绍 + 在线生图
    ├── 💳 购买页          → 套餐选择 + 支付宝扫码
    ├── 📖 API文档页       → 接口说明
    └── ⚙️ 后台配置页      → 配Token + 查订单 + 管密钥

AI 对接（Hermes Agent）：
    ├── 🎨 用户说"画一只猫" → AI 调 API 生图发图
    ├── 💰 用户说"买套餐"  → AI 下单 → 发二维码 → 收款 → 交付密钥
    └── 🔑 全程自动，无需人工
```

---

## ⚙️ 一、服务器环境要求

| 组件 | 版本 | 用途 |
|------|------|------|
| **Nginx** | 1.18+ | Web 服务器 |
| **PHP** | 8.0+ | 后端运行环境 |
| **PHP-FPM** | 随 PHP | PHP 进程管理 |
| **SQLite3** | 3.x | 数据库（密钥/订单） |
| **PHP SQLite3 扩展** | — | PHP 连 SQLite 必须 |
| **qrencode** | — | 生成支付宝二维码（AI 侧用） |

### 安装命令（Ubuntu/Debian）

```bash
# 基础环境
apt update && apt install -y nginx php-fpm php-sqlite3 sqlite3 qrencode

# 检查版本
nginx -v
php -v
php -m | grep sqlite3
sqlite3 --version
```

> 💡 云服务器（腾讯云/阿里云）建议 Ubuntu 24.04 + PHP 8.3。

---

## 📁 二、部署项目

### 1. 上传代码

```bash
# 创建项目目录
mkdir -p /var/www/hsst
cd /var/www/hsst

# 上传项目文件
# 然后将所有文件放进来
```

### 2. 设置权限

```bash
chown -R www-data:www-data /var/www/hsst
chmod -R 755 /var/www/hsst
```

### 3. 创建数据目录和数据库

```bash
cd /var/www/hsst
mkdir -p data
cat data/schema.sql | sqlite3 data/app.db
chown www-data:www-data data/app.db data/
chmod 644 data/app.db
```

### 4. 创建 settings.json（配置存这里）

```bash
echo '{}' > data/settings.json
chown www-data:www-data data/settings.json
chmod 644 data/settings.json
```

---

## 🌐 三、Nginx 配置

创建 `/etc/nginx/sites-available/hsst`：

```nginx
server {
    listen 8080;                          # 端口可自定义
    server_name www.volcengine.com;           # 改成你的域名或IP
    root /var/www/hsst;
    index index.html index.php;

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;  # PHP版本号根据实际改
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 禁止访问 data 目录（防泄露）
    location ^~ /data/ {
        deny all;
        return 404;
    }

    # 静态文件缓存
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 7d;
    }
}
```

启用站点并重启：

```bash
ln -s /etc/nginx/sites-available/hsst /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

> ⚠️ 防火墙要放开对应端口（如 8080）：
> ```bash
> ufw allow 8080/tcp
> ```

---

## 🔥 四、配置火山引擎（图片生成能力）

### 1. 获取火山引擎 API Token

1. 打开 https://console.volcengine.com/ark/region:ark+cn-beijing/
2. 左侧菜单 → **API Key 管理**
3. 点 **「创建 API Key」**
4. 复制生成的 Token（类似 `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`）

### 2. 在后台配置 Token

1. 浏览器打开 `http://你的域名:端口/admin/`
2. 默认密码：`admin888`
3. 粘贴 Token 到 **「API Token」** 字段
4. 点 **「🔍 查询模型」** 拉取可用模型列表
5. 从 **🖼️ 生图模型** 分类选一个（如 `doubao-seedream-5-0-260128`）
6. 点 **「保存」**
7. 去首页输入"一只猫"测试生图 🎉

> ❌ **选错模型的后果：** `doubao-vision-pro-32k` 是视觉理解模型（看图说话），**不能生图**。
> ✅ **正确做法：** 只选 **🖼️ 生图模型** 分类下的模型。

> ⚠️ 火山引擎的 **Seedream 生图模型** 要求最小尺寸 **1920×1920**，小于此尺寸会报错。

---

## 💰 五、配置支付宝当面付（收款）

### 1. 申请支付宝当面付

1. 打开 https://b.alipay.com → **商家中心**
2. 产品中心 → **当面付** → 立即签约
3. 签约完成后，进入 **账户中心** → **开放平台**
4. 创建 **网页/移动应用**（选自用）
5. 添加功能 → **当面付**
6. 获取以下信息：

### 2. 在后台配置支付宝

在 `admin/` 后台页面，找到**支付宝配置**区域填写：

| 字段 | 说明 | 获取位置 |
|------|------|---------|
| **App ID** | 支付宝应用 ID | 开放平台 → 应用详情 |
| **应用私钥** | 你的 RSA2 私钥（PEM） | 自己生成（openssl） |
| **支付宝公钥** | 支付宝的公钥 | 开放平台 → 应用详情 → 接口加签 |

> ⚠️ **应用私钥**是你自己生成的，**支付宝公钥**是支付宝给你的，两个不同！不要填反。

### 3. 生成密钥对（如果还没有）

```bash
# 生成应用私钥 app_private_key.pem
openssl genrsa -out app_private_key.pem 2048

# 从私钥导出公钥（上传到支付宝开放平台）
openssl rsa -in app_private_key.pem -pubout -out app_public_key.pem
```

将 `app_private_key.pem` 的内容（含 `-----BEGIN RSA PRIVATE KEY-----`）填入**应用私钥**字段。

支付宝公钥从开放平台获取：应用详情 → 接口加签 → 查看支付宝公钥 → 复制填入。

### 4. 测试支付

1. 后台点**保存**
2. 去购买页选一个套餐
3. 用支付宝扫码支付
4. **支付成功后告诉 AI** → AI 自动查状态交付密钥 ✅

---

## 🤖 六、对接 AI 自动客服

项目自带了 AI 技能文件，只要把技能装到 Hermes Agent 里，AI 就能自动：

- ↔️ **回答套餐问题**
- 💳 **下单、生成二维码**
- ✅ **查支付、交付密钥**
- 🎨 **用户说"画一只猫"就能生图**

### 安装技能

将 `skill/` 目录下的内容作为 Hermes 技能安装：

```bash
# 复制到 Hermes 技能目录
cp -r skill/SKILL.md ~/.hermes/skills/volcengine-ai-image/SKILL.md
cp -r skill/references/* ~/.hermes/skills/volcengine-ai-image/references/
```

### 修改变量

编辑 `SKILL.md`，修改开头的 variables：

```yaml
variables:
  DOMAIN: "你的域名"          # 如 www.volcengine.com
  PORT: "8080"               # 你设置的端口
  TK1: "TEST1"               # 测试密钥前缀
  TK2: "ABCD"                # 以下为测试密钥主体
  TK3: "EFGH"
  TK4: "IJKL"
  TK5: "MNOP"
```

### 准备测试密钥

在 `api_keys` 表中插入一条测试密钥（is_test=1）：

```bash
cd /var/www/hsst
sqlite3 data/app.db "INSERT INTO api_keys (key_string, type, total_count, daily_limit, is_test, note) VALUES ('TEST1-ABCD-EFGH-IJKL-MNOP', 'count', 10, 10, 1, '测试密钥');"
```

---

## 📤 七、分发技能给用户

部署完成后，你需要把 **AI 技能**（不是项目源码）发给你的用户。用户导入技能后，直接跟自己的 AI 说"画一只猫"，AI 就会调用**你的服务器**生图。

### 1. 下载技能模板

项目包里已经带了技能文件，在 `skill/` 目录下：

```
skill/
├── SKILL.md                   # AI 技能主文件
└── references/
    ├── api-docs.md            # API 文档（AI 参考用）
    └── payment-flow.md        # 支付流程（AI 参考用）
```

### 2. 修改服务器地址

这是最关键的一步！编辑 `skill/SKILL.md`，把开头的 variables 改成**你的服务器信息**：

```yaml
variables:
  DOMAIN: "你的服务器域名或IP"     # ← 必须改成你的！如 www.volcengine.com
  PORT: "8080"                   # ← 你设置的端口
  TK1: "TEST1"                   # 测试密钥（可自定义）
  TK2: "ABCD"                    # 以下保持和数据库插入的一致
  TK3: "EFGH"
  TK4: "IJKL"
  TK5: "MNOP"
```

> ⚠️ **不改域名的话**，用户的 AI 会调用我的服务器而不是你的服务器，钱就进我口袋了！

### 3. 打包并分发

```bash
# 只打包 skill/ 目录，不包含项目源码
cd /var/www/hsst
zip -r /tmp/volcengine-ai-image-skill.zip skill/
```

然后把 `/tmp/volcengine-ai-image-skill.zip` 发给你的用户。用户拿到后：

1. 解压到 `~/.hermes/skills/volcengine-ai-image/`
2. 在 Hermes 中启用该技能
3. 对 AI 说"画一只猫"即可通过你的服务器生图 🎉

### 4. 用户能做什么

| 用户问 AI | AI 会做什么 |
|-----------|------------|
| "套餐有哪些？" | 展示你设置的套餐列表 |
| "我要买体验套餐" | 调你的服务器下单 → 生成二维码 |
| "已支付" | 调你的服务器查支付 → 交付密钥 |
| "画一只猫" | 调你的服务器生图 → 发图片 |

所有 API 调用都指向你的服务器，收益归你 💰

---

## 🧪 八、验证部署

### 1. 首页访问

```
http://你的域名:8080/
```

✅ 看到产品介绍页面，有"在线生图"输入框。

### 2. API 测试

```bash
# 直接用测试密钥生图
curl "http://你的域名:8080/api/generate.php?key=TEST1-ABCD-EFGH-IJKL-MNOP&prompt=一只猫&size=1920x1920"

# 查密钥状态
curl "http://你的域名:8080/api/key_info.php?key=TEST1-ABCD-EFGH-IJKL-MNOP"
```

✅ 返回 JSON，`code=200` 且 `data.images[].url` 有图。

### 3. 支付流程测试

```bash
# 创建测试订单
curl -X POST "http://你的域名:8080/pay/create.php" \
  -H "Content-Type: application/json" \
  -d '{"id":"count_10","name":"体验套餐 (10次)","type":"count","count":10,"price":0.50}'

# 查询订单
curl "http://你的域名:8080/pay/query.php?order_id=上面返回的order_id"
```

### 4. 模型查询测试

```bash
curl "http://你的域名:8080/api/volc_models.php"
```

✅ 返回按类型分组的模型列表。

### 5. AI 对话测试

启用技能后，对 AI 说：
- **"套餐有哪些？"** → AI 展示套餐
- **"我要买体验套餐"** → AI 下单发二维码
- **"已支付"** → AI 查状态交付密钥
- **"画一只猫"** → AI 用付费密钥生图

---

## 🔐 九、安全注意事项

| 项目 | 说明 |
|------|------|
| 🔴 **改后台密码** | 首次登录后在 `admin/index.php` 中修改 `$ADMIN_PASSWORD` |
| 🔴 **禁止外网访问 `/data/`** | Nginx 已配 `deny all`，确认生效 |
| ✅ HTTPS | 强烈建议配 SSL（Let's Encrypt 免费） |
| ✅ Token 不写死 | 所有配置通过后台页面保存到 `settings.json` |

---

## ❓ 十、常见问题

### Q1: 生图返回 403？

```
密钥无效 / 密钥已被禁用
```

原因：
- 测试密钥每日 10 次用完了 → 等明天或购买套餐
- 密钥字符串不完整 → 检查是否被截断
- Token 配错了 → 去后台重新配置火山引擎 Token

### Q2: 生图失败"模型不支持"？

选了 `doubao-vision-pro-32k` 等视觉理解模型。去后台 **重新查询模型**，选 **🖼️ 生图模型** 分类的。

### Q3: 支付二维码扫了付不了？

- 支付宝当面付未签约 → 去支付宝商家中心签约
- App ID / 密钥填错 → 后台重新配置
- 没配支付宝公钥（不是应用公钥） → 检查是否填反

### Q4: SQLite 报错"unable to open database"？

```bash
chown www-data:www-data /var/www/hsst/data/app.db
chmod 644 /var/www/hsst/data/app.db
```

### Q5: 用户说"已支付"但 AI 查不到？

```bash
curl "http://你的域名:8080/pay/query.php?order_id=订单号"
```

- `status=paid` → 已到账，密钥在 `api_key` 字段
- `status=pending` → 可能延迟，过 10 秒再查

---

## 📝 十一、技术栈总结

```
前端          → HTML + CSS + JS（纯静态，无框架）
后端          → PHP 8.0+（无框架，轻量）
数据库        → SQLite 3.x
支付          → 支付宝当面付（扫码付款）
AI 生图       → 火山引擎方舟平台 / Seedream 模型
AI 客服       → Hermes Agent（Nous Research）
密钥格式      → XXXX-XXXX-XXXX-XXXX-XXXXXXXX（32位）
```

---

> 🎉 部署完成！现在你的 AI 生图系统已经跑起来了。
> 用户可以在线生图、购买套餐，AI 自动处理一切。
> 有任何问题直接问 AI — 它连自己都会用 😄
