# 资料小铺 · 数字资料商城开源示例

一个可以自行部署的数字资料商店，包含网页前台、管理后台、购物车、订单和资料交付，适合学习 PHP 项目结构，或在此基础上修改自己的资料销售网站。

采用 PHP、ThinkPHP 8、MySQL 和 Vue 3。页面由服务端模板渲染，没有 npm 安装和前端打包步骤。界面使用暖米色与陶土橙，包含介绍式首页、侧栏资料目录、商品详情和管理后台。

由冬青整理分享。我的 X / Twitter：**[https://x.com/DongQingAsk](https://x.com/DongQingAsk)**。

- 源码仓库：[16159910/resource-shop-example](https://github.com/16159910/resource-shop-example)
- 示例数据库：[database/resource_shop.sql](database/resource_shop.sql)
- 本地验证记录：[验证记录.md](验证记录.md)

## 功能介绍

| 模块 | 功能 |
| --- | --- |
| 资料浏览 | 首页推荐、分类目录、中文全文搜索、排序、详情介绍、章节目录和预览图 |
| 用户账号 | 注册、登录、头像上传、个人资料、查看已购资料和重新下载 |
| 购物与订单 | 加入购物车、多件合并下单、订单查询、待支付订单超时关闭 |
| 资料交付 | 网盘链接、授权文件下载、激活码商品与卡密仓库 |
| 免费资料 | 免费商品、免费资料专区、领取条件设置和领取记录 |
| 管理后台 | 仪表板、资料上下架、分类、用户、订单、卡密和用户提问管理 |
| 网站设置 | 网站名称、Logo、简介、页脚、客服信息和图片水印 |
| 支付接入 | 支付宝网页支付、微信网页支付相关接口，需自行配置商户参数 |
| 邮件与通知 | SMTP 邮件发送、可选的企业微信订单通知 |

分享版已移除会员和分销功能，对应页面、路由及数据库结构也已删除。用户账号用于登录和查询已购资料。

仓库中提供的资料、封面、用户和订单均为虚构示例，支付、邮件、客服和通知接入参数为空，两个支付渠道默认关闭。下载源码并导入示例 SQL 后，可以直接体验浏览、登录、管理后台和示例文件下载；真实支付需要使用自己的商户环境配置、验收。

## 环境要求

| 组件 | 要求 |
| --- | --- |
| PHP | **8.2 或以上**，按当前 `composer.lock` 的运行依赖要求 |
| Composer | 2.x |
| MySQL | 5.7 或以上，支持 InnoDB、utf8mb4 及 ngram 全文索引 |
| PHP 扩展 | PDO、pdo_mysql、mbstring、OpenSSL、cURL、fileinfo、iconv；图片处理使用 GD |
| Web 服务 | 本地可用 ThinkPHP 开发服务器；服务器部署可使用 Nginx + PHP-FPM |
| Node.js | 可选，仅运行模板回归检查时需要 |

下面安装命令使用 `--no-dev`。如需安装当前锁文件中的开发调试依赖，PHP 需达到 8.4.1。

## 安装与启动

以下命令均在项目根目录执行，也就是包含 `think`、`composer.json` 和 `.env.example` 的目录。

### 1. 获取源码并安装依赖

```sh
git clone https://github.com/16159910/resource-shop-example.git
cd resource-shop-example
composer install --no-dev --prefer-dist
```

也可以在 GitHub 点击「Code → Download ZIP」，解压后进入项目目录，再执行 Composer 命令。依赖目录 `vendor/` 不随源码提交，由 Composer 安装。

### 2. 新建数据库

新建一个空库，例如 `resource_shop`。以下示例使用 MySQL 管理账号交互式输入密码：

```sh
mysql -u root -p
```

进入 MySQL 后执行：

```sql
CREATE DATABASE resource_shop
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
EXIT;
```

如果使用宝塔、phpMyAdmin、Navicat 等工具，直接新建数据库，字符集选择 `utf8mb4`，并记录数据库名称、连接账号和密码。建议为该项目创建只访问此数据库的账号。

### 3. 导入示例数据库

命令行方式：

```sh
mysql --default-character-set=utf8mb4 -u YOUR_DB_USER -p resource_shop < database/resource_shop.sql
```

将 `YOUR_DB_USER` 换成实际 MySQL 用户，执行后输入该用户的数据库密码。如果 MySQL 不在本机默认端口，增加 `-h YOUR_DB_HOST -P YOUR_DB_PORT`。

图形工具方式：

1. 连接 MySQL，选中刚创建的空数据库。
2. 使用「导入」或「运行 SQL 文件」。
3. 选择本项目的 `database/resource_shop.sql`，编码选 UTF-8，执行导入。
4. 刷新表列表，应看到 **17 张 `shop_` 开头的表**。

SQL 包含完整建表语句和示例数据，不需要再寻找其他数据库。文件不创建数据库，也不切换数据库；请在选定的新空库中导入。已经导入过的库不要重复执行，重新安装时使用另一个空库。

可以执行下面的 SQL 确认结果：

```sql
USE resource_shop;
SHOW TABLES;
SELECT COUNT(*) AS material_count FROM shop_material;
```

初始资料数量应为 `12`。

### 4. 填写网站数据库连接

```sh
cp .env.example .env
```

编辑 `.env` 中的数据库部分：

```dotenv
APP_DEBUG = false
DB_TYPE = mysql
DB_HOST = 127.0.0.1
DB_PORT = 3306
DB_NAME = resource_shop
DB_USER = your_database_user
DB_PASS = your_database_password
DB_CHARSET = utf8mb4
DB_PREFIX = shop_
```

- `DB_HOST`、`DB_PORT`：MySQL 的实际地址和端口。
- `DB_NAME`：刚才导入 SQL 的数据库名称。
- `DB_USER`、`DB_PASS`：有权访问该数据库的 MySQL 账号和密码；特殊字符密码可使用双引号包住整个值。
- `DB_PREFIX`：示例库使用 `shop_`，保持一致。

**这里填写的是 MySQL 连接信息。`dongqing / dongqingask` 是网站后台登录账号，不能当作数据库连接信息填写。**

生成一个随机签名密钥：

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

将输出的 64 位十六进制字符串填到 `.env` 的 `API_TOKEN_SECRET`，替换模板占位内容。保留模板中的会话名称和缓存前缀；如部署多个副本，请为每个站点使用不同名称和前缀。

### 5. 启动本地网站

```sh
php think run --host=127.0.0.1 --port=8000
```

保持终端运行，然后访问：

| 页面 | 地址 |
| --- | --- |
| 前台首页 | http://127.0.0.1:8000/ |
| 资料目录 | http://127.0.0.1:8000/list |
| 免费资料 | http://127.0.0.1:8000/free |
| 管理后台 | http://127.0.0.1:8000/admin |

端口占用时可以把 `8000` 换成其他空闲端口，访问地址也相应修改。开发服务器用于本地预览，公开部署请按下面的 Nginx / PHP-FPM 方式配置。

## 示例账号与体验方法

| 用途 | 账号 | 密码 |
| --- | --- | --- |
| 管理后台 | `dongqing` | `dongqingask` |
| 前台示例用户 | `demo@example.com` | `demo123456` |

后台登录后，可在侧栏底部点击「修改密码」。公开部署时先修改示例管理员密码。

建议按以下顺序体验：

1. 在首页和资料目录查看分类、搜索、详情、目录和预览图。
2. 点击前台登录入口，使用示例用户登录；在「我的资料」中下载已经购买的两份示例资料。
3. 找到价格为零的商品，点击领取；或进入「免费资料」体验领取和下载。
4. 把付费资料加入购物车，体验合并下单和订单查询。默认没有启用支付渠道，付费订单不会自动付款。
5. 打开 `/admin`，使用后台示例账号查看商品、用户、订单和设置页。

## 后台怎么使用

### 发布普通资料

1. 在「分类管理」中新建分类，设置名称和排序。
2. 进入「资料管理」，新增资料，填写标题、分类、简介、详情、价格和文件格式等内容。
3. 上传封面及预览图，填写章节目录。
4. 选择交付方式，上传资料文件或填写自己的网盘链接及提取信息。
5. 保存并上架，再到前台目录检查展示和下载结果。

文件交付存放在 `protected/`，通过业务接口校验后提供下载。Web 服务器的站点根目录应设为 `public/`。

### 发布卡密商品与免费资料

- 卡密商品：先在「资料管理」建立对应商品并选择激活码类型，再到「卡密仓库」选择商品、导入卡密，检查库存后上架。
- 免费资料：进入「免费资料」，新增资料、设置领取方式和条件、配置交付内容，再上架。示例库已提供两条可体验的数据。
- 「订单管理」用于查询交易，「用户管理」用于编辑用户、重置密码和启停账号，「用户提问」用于查看和回复反馈。

### 修改站名、联系方式和邮件

进入「网站配置」即可修改网站名称、Logo、图标、简介、页脚、备案信息、客服微信、客服二维码、水印、SMTP 及企业微信机器人地址，填写后点击「保存配置」。

SMTP 使用自己的邮箱服务商提供的服务器、端口、邮箱账号和授权码。仅浏览网页和体验本地示例下载时，可以保持为空；需要发送邮件时再配置并实际测试。

## 支付配置

「后台 → 支付设置」包含支付宝和微信两套表单。示例库仅保留渠道记录，商户参数均为空且未启用。

| 渠道 | 需要填写的主要信息 |
| --- | --- |
| 支付宝 | 应用 App ID、应用私钥、支付宝公钥、网关地址 |
| 微信支付 | 应用 AppID、商户号、API v3 密钥、商户证书序列号、商户私钥文件路径、支付通知地址；微信内网页授权还需要公众号 AppSecret |

支付宝异步通知路由为 `/pay/notify/alipay`，同步返回路由为 `/pay/return`；微信异步通知路由为 `/pay/notify/wechat`。回调使用自己的可公开访问域名，例如 `https://example.com/pay/notify/wechat`。微信 H5 和公众号网页授权还需按商户平台及公众号要求配置相应域名。

商户私钥文件应放在 `protected/certs/` 等 Web 根目录之外的位置，表单中填写 PHP 进程可读取的服务器文件路径。填写并保存配置、完成商户侧配置后，再启用渠道并测试完整的下单、付款、回调和下载流程。

代码包含微信 H5、JSAPI 和 Native 场景处理；当前后台表单没有提供 Native 的 `native_enabled` 开关，默认不展示电脑端微信扫码渠道。支付宝和微信均未在本分享版进行真实付款验收，接入结果以自己的支付环境测试为准。

## 部署到服务器

1. 上传项目，使用 PHP 8.2 或以上版本执行 `composer install --no-dev --prefer-dist`。
2. 新建数据库、导入 `database/resource_shop.sql`，填写服务器自己的 `.env`。
3. 将网站运行目录设置为项目中的 **`public/`**。
4. 配置 PHP-FPM 和 URL 重写。完整 Nginx 示例见 [deploy/nginx.conf.example](deploy/nginx.conf.example)，需替换域名、项目路径和 PHP-FPM 地址。
5. 让 PHP 运行用户对 `runtime/`、`public/uploads/` 和 `protected/` 中需要写入的目录有权限。
6. 配置 HTTPS 后，将 `.env` 中的 `COOKIE_SECURE` 改为 `true`；本地 HTTP 预览保持 `false`。生产环境保持 `APP_DEBUG = false`。
7. 修改后台示例密码，配置自己的站名和需要启用的服务，再检查前台、后台、上传、下载及支付回调。

Nginx 的主要重写规则如下，需放在对应站点的 `server` 中：

```nginx
location / {
    try_files $uri $uri/ /index.php?s=$uri&$query_string;
}
```

使用宝塔时，选择对应 PHP 版本，将「网站目录 → 运行目录」设为 `/public`，并在站点伪静态中配置上述重写规则。PHP 命令行版本与站点使用的 PHP 版本应一致。

可选配置每分钟执行 `php think order:close`，及时关闭超过 10 分钟未付款的订单。前台查询和支付流程也会检查过期订单。

## 示例数据库和文件说明

示例 SQL 包含：

- 17 张基础业务表，统一使用 `shop_` 前缀。
- 4 个分类、12 份资料、24 条预览记录。
- 6 个虚构用户、8 笔初始示例订单、2 份免费资料。
- 1 个后台管理员以及关闭状态的支付宝、微信渠道。

`public/uploads/samples/` 中是示例 SVG 封面和预览图；`protected/samples/` 中有 14 个示例 ZIP。压缩包中只有虚构场景、样例文字和 CSV 记录，示例联系方式使用 `example.com`。

导入后的商品浏览、后台操作和下单会改变本机数据库；仓库中的 SQL 是初始数据文件。再次分享时继续使用该初始 SQL，或单独生成经过检查的演示数据。

## 项目目录与修改入口

```text
app/home/                   网页端控制器、业务和模板
app/admin/                  管理后台控制器、业务和模板
app/common/                 公共模型、服务和控制台命令
config/                     应用、数据库、缓存等配置
public/                     Web 运行目录
public/static/              CSS、脚本、图标和本地前端库
public/uploads/samples/     示例封面和预览图
protected/samples/          示例下载文件
runtime/                    自动生成的日志、会话与模板缓存
database/resource_shop.sql  完整示例建表语句和数据
deploy/nginx.conf.example   Nginx 配置示例
tests/                      模板回归检查
.env.example                配置模板
```

修改前台页面从 `app/home/view/` 开始，后台页面从 `app/admin/view/` 开始；路由分别在对应应用的 `route/app.php` 中。数据库连接配置位于 `config/database.php`，实际连接值读取 `.env`。

`.gitignore` 已排除 `.env`、`vendor/`、运行缓存、后续上传的业务文件和支付证书；项目自带的示例封面、预览图和 ZIP 文件保留在仓库中。

## 常见问题

| 现象 | 检查方法 |
| --- | --- |
| 提示找不到 `vendor/autoload.php` | 在项目根目录执行 `composer install --no-dev --prefer-dist` |
| Composer 提示 PHP 版本不满足 | 当前运行依赖需要 PHP 8.2+；检查 `php -v`，并确认没有误装开发依赖 |
| 数据库连接失败 / Access denied | 检查 `.env` 的数据库地址、名称、账号、密码和数据库授权 |
| 找不到 `shop_` 表 | 确认 SQL 导入到了 `DB_NAME` 对应数据库，且 `DB_PREFIX = shop_` |
| 导入时提示表已存在 | 当前库已经有表；为本示例新建空库，再导入一次 |
| 首页能开，其他地址 404 | 检查运行目录是否为 `public/`、Nginx 重写是否生效 |
| 中文搜索异常 | 确认 MySQL 支持 ngram，示例 SQL 中的全文索引已成功创建 |
| 登录状态保持失败 | 本地 HTTP 使用 `COOKIE_SECURE = false`，并检查会话目录写权限 |
| 页面仍显示修改前内容 | 在项目根目录执行 `php think clear`，再刷新浏览器 |
| 找不到可用支付方式 | 默认渠道关闭；启用后仍需商户参数完整，微信渠道还会根据访问环境筛选 |
| 上传或下载报权限错误 | 检查 PHP 运行用户对上传目录、`protected/` 和 `runtime/` 的访问权限 |

## 验证与开发检查

已在本机 PHP 8.5.7、MySQL 5.7.44 和 Edge 桌面浏览器中验证空库导入、后台和前台登录、中文搜索、购物车、合并下单、免费领取、已购下载，以及网站配置和支付设置的显示与保存。详细范围见 [验证记录.md](验证记录.md)。

如安装了 Node.js，可运行使用项目真实 Vue 编译器的模板回归检查：

```sh
node tests/compile-admin-templates.cjs
```

该检查覆盖网站配置、支付设置、用户和订单页面的编译与首次渲染。移动端包含响应式样式，尚未完成真机验收。

## 作者与许可

冬青的 X / Twitter：**[https://x.com/DongQingAsk](https://x.com/DongQingAsk)**。

欢迎阅读源码、参考项目结构和提交问题。问题反馈可通过本仓库 [Issues](https://github.com/16159910/resource-shop-example/issues) 提交，请说明运行环境、操作步骤和错误信息。

项目保留原有的 [LICENSE.txt](LICENSE.txt) 及依赖许可信息。ThinkPHP 及各依赖的权利声明以对应项目附带的许可证为准。
