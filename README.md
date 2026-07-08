# 🤖 AI部署指南 — 火山AI生图万能模板

> 写给 AI 看的部署流程。用户给你项目包，你帮用户部署到服务器上。
> 每完成一步告知用户进度，出问题先自动修，不行再问用户。

---

## 📋 部署前必读

**你需要：** 用户服务器 SSH 权限 + 项目包（zip）  
**后续需要用户提供：** 火山引擎 Token + 支付宝信息（部署完再要，不等）

---

## 第①步 — 检测公网IP + 全局替换域名

```bash
# 1. 获取公网IP
PUBLIC_IP=$(curl -s --connect-timeout 5 ifconfig.me 2>/dev/null || curl -s --connect-timeout 5 ip.sb 2>/dev/null)

# 2. 没公网IP → 终止，告知用户换服务器
if [ -z "$PUBLIC_IP" ]; then
  echo "❌ 该服务器没有公网IP！用户无法连接，请换一台有公网IP的云服务器。"
  exit 1
fi
echo "✅ 公网IP: $PUBLIC_IP"

# 3. ⚡ 全局替换：把所有 www.volcengine.com 换成用户IP（一步到位）
cd /var/www/hsst
find . -type f \( -name "*.md" -o -name "*.html" -o -name "*.php" \) -exec sed -i "s/www\.volcengine\.com/$PUBLIC_IP/g" {} +

# 4. 验证无遗漏
grep -r "www.volcengine.com" /var/www/hsst/ --include="*.md" --include="*.html" --include="*.php" && echo "❌ 还有遗漏！" || echo "✅ 全局替换完成"
```

> 💡 **一次替换，永不会漏。** SKILL.md、Nginx配置、首页、文档全部自动改用新IP。
>
> 💡 **后续也可以自定义域名。** 如果你有自己的域名（比如 `pic.mysite.com`），跟我说一声，我帮你把IP换成域名，同样全局替换一次搞定。

---

## 第②步 — 环境检查 + 安装依赖

```bash
# 批量检查
nginx -v 2>/dev/null || apt install -y nginx
php -v 2>/dev/null || apt install -y php-fpm php-sqlite3
php -m | grep -q sqlite3 || apt install -y php-sqlite3
sqlite3 --version 2>/dev/null || apt install -y sqlite3
qrencode --version 2>/dev/null || apt install -y qrencode
systemctl status php*-fpm 2>/dev/null | head -1 || systemctl start php8.3-fpm
```

**遇到问题：**
- `apt install` 失败 → 检查 sudo 权限，或让用户以 root 执行
- PHP < 8.0 → `apt install -y php8.3-fpm php8.3-sqlite3`
- PHP-FPM 没跑 → `systemctl start php8.3-fpm && systemctl enable php8.3-fpm`

---

## 第③步 — 部署项目文件 + Nginx

```bash
# 创建目录
mkdir -p /var/www/hsst
# 解压项目（如果zip在其他路径，让用户提供）
unzip -o /tmp/volcengine-image-project.zip -d /var/www/hsst/
# 权限
chown -R www-data:www-data /var/www/hsst
chmod -R 755 /var/www/hsst
# 数据库
cd /var/www/hsst && mkdir -p data
cat data/schema.sql | sqlite3 data/app.db
echo '{}' > data/settings.json
chown www-data:www-data data/app.db data/settings.json data/
chmod 644 data/app.db data/settings.json
```

**配置 Nginx：** 创建 `/etc/nginx/sites-available/hsst`

```nginx
server {
    listen {PORT};
    server_name {PUBLIC_IP};
    root /var/www/hsst;
    index index.html index.php;
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php{VERSION}-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ^~ /data/ { deny all; return 404; }
}
```

```bash
# {PORT}问用户（默认8080），{VERSION}从 php -v 取
ln -sf /etc/nginx/sites-available/hsst /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
ufw allow {PORT}/tcp 2>/dev/null
```

❌ `nginx -t` 报错 → 最常见是 `fastcgi_pass` 的 sock 路径不对 → `ls /var/run/php/` 看实际文件名

✅ **首页可访问：** `http://{PUBLIC_IP}:{PORT}/`

---

## 第④步 — 安装AI技能 + 打包技能包（一气呵成）

```bash
# 安装技能到 Hermes
mkdir -p ~/.hermes/skills/volcengine-ai-image/references
cp /var/www/hsst/skill/SKILL.md ~/.hermes/skills/volcengine-ai-image/SKILL.md
cp /var/www/hsst/skill/references/* ~/.hermes/skills/volcengine-ai-image/references/

# 插入测试密钥
sqlite3 /var/www/hsst/data/app.db "INSERT OR IGNORE INTO api_keys (key_string, type, total_count, daily_limit, is_test, note) VALUES ('TEST1-ABCD-EFGH-IJKL-MNOP', 'count', 10, 10, 1, '测试密钥');"

# ⚡ 打包技能包（正确结构：有 references/，无 skill/ 外层）
cd /var/www/hsst/skill
rm -f /tmp/volcengine-ai-image-skill.zip
zip -r /tmp/volcengine-ai-image-skill.zip SKILL.md references/
```

✅ 解压后：`SKILL.md` + `references/api-docs.md` + `references/payment-flow.md`  
❌ 不能有 `skill/` 前缀，也不能拍平（SKILL.md 引用路径会断）

---

## 第⑤步 — 基础验证

```bash
# 首页
curl -s -o /dev/null -w "%{http_code}" "http://localhost:{PORT}/" | grep -q 200 && echo "✅ 首页" || echo "❌ 首页"
# 密钥查询
curl -s "http://localhost:{PORT}/api/key_info.php?key=TEST1-ABCD-EFGH-IJKL-MNOP" | grep -q '"code": 200' && echo "✅ 密钥查询" || echo "❌ 密钥查询"
# 支付页
curl -s -o /dev/null -w "%{http_code}" "http://localhost:{PORT}/pay/" | grep -q 200 && echo "✅ 支付页" || echo "❌ 支付页"
# 后台页
curl -s -o /dev/null -w "%{http_code}" "http://localhost:{PORT}/admin/" | grep -q 200 && echo "✅ 后台页" || echo "❌ 后台页"
```

全部 ✅ → 继续。有 ❌ → 排查后再继续。

---

## 第⑥步 — 交付！（4道强制检查，全过才能发）

### 检查清单（逐项执行，少一项不准交付）

```bash
# 🔴 检查一：全局替换已生效
grep "DOMAIN:" /var/www/hsst/skill/SKILL.md | grep -q "$PUBLIC_IP" && echo "✅ 一" || echo "❌ 一：域名没改，返回第①步！"
# 🔴 检查二：技能包结构正确
unzip -l /tmp/volcengine-ai-image-skill.zip | grep -q "^.*SKILL.md$" && unzip -l /tmp/volcengine-ai-image-skill.zip | grep -q "references/api-docs.md" && echo "✅ 二" || echo "❌ 二：结构不对，重新打包！"
# 🔴 检查三：zip里的域名也正确
unzip -p /tmp/volcengine-ai-image-skill.zip SKILL.md | grep "DOMAIN:" | grep -q "$PUBLIC_IP" && echo "✅ 三" || echo "❌ 三：zip内域名不对，重新打包！"
# 🔴 检查四：文件已附带（这条靠你自觉，MEDIA 发出去才算）
echo "✅ 四（确认已用MEDIA发送文件）"
```

### 全部通过后，发出以下信息（必须附带文件！）：

> ✅ **部署完成！**
>
> **1️⃣ 项目地址** — http://{PUBLIC_IP}:{PORT}/
> **2️⃣ 后台管理** — http://{PUBLIC_IP}:{PORT}/admin/ （密码 admin888）
> **3️⃣ 📎 技能包** — volcengine-ai-image-skill.zip
>
> **你可以把这个技能包发给任何用户使用。** 别人解压启用后，对 AI 说"画一只猫"就能调用你的服务器生图。免费体验用测试密钥每日10次，付费找你买套餐。所有请求指向你的服务器，**收益归你**。

> ⚠️ **AI自查：** 上面这条消息必须 **带有 MEDIA 文件附件**，不能只有文字！

---

## 第⑦步 — 提醒后续配置（不着急，以后再说）

> ⚠️ 目前还不能生图和收款，还需要配两样东西。**可以找我帮你配，也可以自己去后台配：**
>
> **① 火山Token（配了才能生图）**
> 让我配 → 把 Token 发给我 | 自己配 → 后台 → 火山引擎配置 → 粘贴Token + 选模型
> 获取：https://console.volcengine.com/ark/ → API Key管理 → 创建
>
> **② 支付宝当面付（配了才能收款）**
> 让我配 → 把 App ID、应用私钥、支付宝公钥发给我 | 自己配 → 后台 → 支付宝配置
> 申请：https://b.alipay.com → 商家中心 → 当面付 → 签约
>
> **不着急，什么时候准备好了找我。**

### 如果用户找你配火山Token

```bash
cat > /var/www/hsst/data/settings.json << EOF
{
  "volc_api_token": "用户给你的Token",
  "volc_model": "doubao-seedream-5-0-260128"
}
EOF
chown www-data:www-data /var/www/hsst/data/settings.json
curl -s "http://localhost:{PORT}/api/volc_models.php" | python3 -m json.tool  # 查模型
curl -s "http://localhost:{PORT}/api/generate.php?key=TEST1-ABCD-EFGH-IJKL-MNOP&prompt=一只猫&size=1920x1920"  # 测试
```

> **不要选 `doubao-vision-pro-32k`！** 那是视觉理解模型，看图用的不能生图。

### 如果用户找你配支付宝

让用户去 `http://{PUBLIC_IP}:{PORT}/admin/` → 支付宝配置 → 填三个字段保存 → 测试支付。

---

## 🌟 第⑧步 — 告诉用户这是万能模板

> 📢 **这套不只能卖生图，它是万能模板！** 同样的支付+密钥+AI客服系统，换个API就能卖别的。
>
> **想卖什么就跟AI说：**
>
> | 对AI说 | 就能卖 |
> |--------|--------|
> | "帮我改成卖AI视频生成" | 🎬 文字生成短视频 |
> | "帮我改成卖AI电商图" | 🖼️ 白底图→模特图/场景图 |
> | "帮我改成卖AI音乐" | 🎵 文字→音乐/beat |
> | "帮我改成卖AI文案" | 📝 自动写小红书/抖音文案 |
> | "帮我改成卖AI配音" | 🎤 文字→语音解说 |
> | "帮我改成卖AI数字人" | 🎭 照片→说话视频 |
> | "帮我改成卖AI证件照" | 📸 自拍→换底色/换装 |
>
> **支付也可以加：**
>
> | 对AI说 | 效果 |
> |--------|------|
> | "帮我加上微信支付" | 💚 多一个收款通道 |
> | "帮我加上易支付" | 🏪 个人免签约支付 |
> | "帮我加上USDT支付" | 💳 加密货币收款 |
>
> **加功能、修bug、改界面：**
>
> ```
> "帮我加个使用统计页面"
> "生图接口报错了帮我修一下"
> "首页帮我改得好看一点"
> "套餐太少了帮我多加几个"
> ```
>
> **你只管说，AI自动改代码、改技能、测试、交付。不用写一行代码。** 🚀

---

## 🔧 常见问题速查

| 问题 | 处理 |
|------|------|
| Nginx -t 报错 | `fastcgi_pass` sock 路径不对，`ls /var/run/php/` 查 |
| PHP 不执行（下载/空白） | `systemctl status php*-fpm`，检查 fastcgi_pass 版本号 |
| 数据库报错 | `chown www-data:www-data /var/www/hsst/data/app.db` |
| 生图 403 | Token 没配或配错 → 检查 settings.json |
| 生图失败（模型不支持） | 去后台换 🖼️ 生图模型 |
| 支付创建报错 | 支付宝没配好 |
| 端口不通 | `ufw allow` + 云安全组放行 |
| 技能不生效 | 检查 `~/.hermes/skills/volcengine-ai-image/SKILL.md` 路径 |
| 查不到支付 | 支付宝到账有延迟，过10秒再查 |

---

## ✅ 部署确认清单

```
☐ 公网IP检测 + 全局替换 www.volcengine.com → {PUBLIC_IP} ✅
☐ 首页 http://{PUBLIC_IP}:{PORT}/ → 200 ✅
☐ AI技能已安装 + 测试密钥已插入 ✅
☐ 技能包已打包（有 references/，无 skill/ 层） ✅
☐ 🔴 强制检查一：DOMAIN 已验证 ✅
☐ 🔴 强制检查二：zip结构正确 ✅
☐ 🔴 强制检查三：zip内域名正确 ✅
☐ 🔴 强制检查四：文件已用 MEDIA 发出 ✅
☐ 已发三样：项目地址 + 后台地址 + 技能包 ✅
☐ 已提醒后续配 Token + 支付宝 ✅
☐ 已告知这是万能模板 ✅
```

|---

## 第⑨步 — 用户说"改成卖别的产品"（万能模板转换流程）

这套项目的核心是**卖API的完整基础设施**（支付+密钥+AI客服），文章里已经写清楚了。如果用户说"我现在是卖生图的，想改成卖XXX"，你要按下面的流程把整套项目转换过去。

### 前置条件

用户需要提供：**目标产品的 API 接口文档**（火山引擎、百度、腾讯、OpenAI、你自己的系统……只要是 HTTP API 都行）

### 转换八步流程

按顺序执行，一步做完再做下一步：

```text
第①步 读文档     → 理解新API的鉴权方式、请求参数、返回格式
第②步 改核心API  → api/generate.php：把生图API调用换成新API的调用
第③步 改套餐     → admin/config.php：根据新产品的成本重新设定价
第④步 改首页     → index.html：产品介绍、示例展示换成新产品的
第⑤步 改技能文档 → skill/SKILL.md + references/ 更新为新产品说明
第⑥步 更新AI技能 → 把改好的 skill/ 同步到 ~/.hermes/skills/（重要！）
第⑦步 测试       → curl 调不通就修，修到能跑
第⑧步 打包新技能 → zip 新产品的技能包（跟旧的不一样）
第⑨步 交付       → 发项目地址 + 新技能包给用户
```

### 每个步骤的具体操作

#### 第①步 — 读文档

```bash
# 用户给了文档或文档URL，先读
curl -s "用户给的文档URL"  # 如果是网页文档
# 或者直接问用户要关键信息

# 搞清楚这三件事：
# 1. 鉴权方式（API Key / Bearer Token / 签名）
# 2. 请求参数（必填和可选）
# 3. 返回格式（JSON字段结构）
```

#### 第②步 — 改 generate.php

```bash
# 先读当前文件
cat /var/www/hsst/api/generate.php

# 核心要改的地方：
```

| 改什么 | 原来的（生图） | 改成（例：视频生成） |
|--------|---------------|-------------------|
| API 地址 | `VOLC_API_BASE . '/images/generations'` | 新API的 endpoint |
| 请求体 | `{model, prompt, n, size}` | 新API要求的请求体格式 |
| 鉴权头 | `Authorization: Bearer xxx` | 新API的鉴权方式 |
| 超时时间 | `CURLOPT_TIMEOUT => 120` | 如果生成耗时更长（如视频），改大 |
| 返回提取 | `$result['data'][0]['url']` | 新API的返回字段路径 |

> ⚠️ **保留不动的地方**：参数校验（key/prompt）、密钥验证（SQLite3查DB）、计次逻辑（used_count递增）、JSON响应格式。这些是基础设施，换产品也不用改。

#### 第③步 — 改套餐定价

```bash
# 改 admin/config.php 里的套餐数组
nano /var/www/hsst/admin/config.php

# 根据新API的成本重新定价
# 例：视频生成API调用成本是生图的5倍
# 原来体验套餐¥0.5 → 改成¥2.5
```

#### 第④步 — 改首页

```bash
# 改产品名称、描述、示例展示
nano /var/www/hsst/index.html

# 文章里"发 Skill 给用户"那节的结构可以保留
# 只要把产品相关的文字/图片换掉
```

#### 第⑤步 — 改技能文档

```bash
# 改 SKILL.md - 产品名、描述、API路径
nano /var/www/hsst/skill/SKILL.md

# 改 references/api-docs.md - API文档说明
nano /var/www/hsst/skill/references/api-docs.md

# 改 references/payment-flow.md 一般不用动（支付流程通用）
# 除非新产品支付方式不一样
```

| 文件 | 要改什么 |
|------|---------|
| `skill/SKILL.md` | name、description、when_to_use、API路径、使用示例 |
| `references/api-docs.md` | API地址、参数说明、返回示例全部换成新产品的 |
| `references/payment-flow.md` | 一般不动（支付流程通用） |

#### 第⑤步-b — 更新AI自己的技能（重要！）

项目中 skill/ 目录改完后，**还要更新 AI 自己正在用的技能**，否则AI不知道产品已经换了：

```bash
# 把改好的技能文件同步到 Hermes 技能目录
cp /var/www/hsst/skill/SKILL.md ~/.hermes/skills/volcengine-ai-image/SKILL.md
cp /var/www/hsst/skill/references/* ~/.hermes/skills/volcengine-ai-image/references/

# 验证
cat ~/.hermes/skills/volcengine-ai-image/SKILL.md | head -5
# 确认 name 和 description 已经是新产品的了
```

> ⚠️ **不更新这步的话**：AI 自己还认为自己在卖生图，用户说"生成视频"AI 会说没这个能力。所以改完 skill/ 后必须同步到 `~/.hermes/skills/` 下。

#### 第⑥步 — 测试

```bash
# 1. 测试新API是否通
curl -s "http://localhost:{PORT}/api/generate.php?key=TEST1-ABCD-EFGH-IJKL-MNOP&prompt=测试"

# 2. 检查返回格式
# 应该返回 {"code": 200, "data": {...}, "key_info": {...}}

# 3. 密钥查询是否正常
curl -s "http://localhost:{PORT}/api/key_info.php?key=TEST1-ABCD-EFGH-IJKL-MNOP"

# 4. 支付页是否正常
curl -s -o /dev/null -w "%{http_code}" "http://localhost:{PORT}/pay/"

# 如果有报错 → 看 PHP 错误日志
tail -20 /var/log/nginx/error.log
```

#### 第⑦步 — 打包新技能包

改完产品后，**要生成新的技能包**（不是原来那个了）：

```bash
cd /var/www/hsst/skill
rm -f /tmp/volcengine-ai-image-skill.zip
zip -r /tmp/volcengine-ai-image-skill.zip SKILL.md references/
# 确认结构正确
unzip -l /tmp/volcengine-ai-image-skill.zip
# 应该是：
#   SKILL.md                          # ← 已经是新产品描述
#   references/api-docs.md            # ← 已经是新API文档
#   references/payment-flow.md
```

> ⚠️ **这个 zip 跟原来卖生图时的 zip 完全不一样了**。里面的 SKILL.md 描述的是新产品，api-docs.md 是新的API接口。不能把旧技能包发给用户。

#### 第⑧步 — 交付

> ✅ **转换完成！**
>
> **1️⃣ 项目地址** — http://{PUBLIC_IP}:{PORT}/
> **2️⃣ 后台管理** — http://{PUBLIC_IP}:{PORT}/admin/
> **3️⃣ 📎 新技能包** — 见附件（跟原来的不一样，是新产品的）
>
> 已从「AI生图」转换成「AI视频生成」，支付、密钥、AI客服系统全部保留。
> **你自己的 AI 技能已同步更新**，现在直接说"生成一个视频"就能用。
> **发技能包给新用户**，他们导入后也能用你的新服务。

### 常见转换示例速查

| 用户说"帮我改成卖…" | 主要改什么 | 注意事项 |
|-------------------|-----------|---------|
| 🎬 AI视频生成 | generate.php 调视频API、套餐定价×5、超时改300s | 视频生成慢，返回可能是异步任务ID |
| 🎵 AI音乐生成 | generate.php 调音乐API、返回文件换音频URL | 注意音频格式和大小 |
| 🎤 AI配音/语音合成 | generate.php 调TTS API、prompt参数改成text | 按字数计费，套餐改成按字数 |
| 🖼️ AI电商换背景 | generate.php 改图生图API（多传一张参考图） | 参数多一个 image_url |
| 🎭 AI数字人 | generate.php 调数字人API、返回视频URL | 可能需上传照片，加文件上传逻辑 |
| 📸 AI证件照 | generate.php 调证件照API | 尺寸固定，不需要size参数 |
| 🔧 你自己的API | generate.php 改成调你的系统 | 按你的API文档来改 |

### 核心原则

- **不动基础设施**。pay/（支付）、admin/（后台）、data/（数据库）、密钥鉴权逻辑——这些不管换什么产品都不用改
- **只动产品层**。api/generate.php（API调用）、index.html（首页）、skill/（技能文档）——这些才是换产品要改的地方
- **每改一步都验证**。改完 generate.php 就 curl 测一次，不要等全改完才测
- **用户给了文档就按文档来**，没给就问用户要。不要自己猜API怎么调

> 🎉 收工！
