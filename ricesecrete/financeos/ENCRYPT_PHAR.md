# financeos_loader — 加密 PHP 源码加载器

## 流程

```
明文 PHP  →  encrypt_php.php  →  FOSENC01 密文
                                    ↓
                         financeos_loader 扩展在编译期解密
                                    ↓
                         build_encrypted_phar.php → webman.phar
                                    ↓
                         服务器: php webman.phar start
```

## 1. 编译扩展（Linux，需 phpize / OpenSSL）

```bash
cd ext/financeos_loader
phpize
./configure --enable-financeos-loader
make
sudo make install
```

`php.ini`：

```ini
extension=financeos_loader.so
financeos_loader.key=YOUR_SECRET
```

Windows 需 PHP 开发包 + OpenSSL，用 `config.w32` 在 PHP SDK 下编译。

## 2. 加密并打 PHAR

```bash
# 密钥必须与 php.ini 中 financeos_loader.key 一致
# Windows / phar.readonly=On 时：
php -d phar.readonly=0 scripts/build_encrypted_phar.php --key=YOUR_SECRET
```

脚本顺序：复制到 `build/stage` → 加密 → **直接打 PHAR**（不再调用 `webman build:phar`，避免 CLI 加载密文失败）。

产物：

- `build/webman.phar`
- `build/financeos_loader.ini.example`

仅加密 `app/`、`support/`、`config/`；`vendor/` 保持明文以便 Composer 生态运行。

## 3. 服务器运行

### 3.1 安装扩展（必须）

加密 PHAR **不能**在没有扩展的环境运行。报错示例：

```text
Parse error: unexpected token "<" in phar://.../config/app.php on line 1
```

表示 PHP 把密文当源码编译了，**不是密钥错误**（密钥错会报 `financeos_loader: decrypt failed`）。

Linux（含宝塔）：

```bash
cd ext/financeos_loader
phpize
./configure --enable-financeos-loader
make && sudo make install
```

在 **运行 `php webman.phar` 所用的那份 PHP** 的 `php.ini` 中加入（CLI 与 FPM 可能是不同 ini）：

```ini
extension=financeos_loader.so
financeos_loader.key=lax1024
```

宝塔：软件商店 → 对应 PHP 版本 → 设置 → 安装扩展（需自行上传编译好的 `.so`）或 SSH 编译后放到 `extension_dir`，再在配置文件中追加上面两行 → 重载 PHP / 重启 Workerman。

验证：

```bash
php -m | grep financeos_loader
php -r "echo ini_get('financeos_loader.key') ? 'key ok' : 'key empty';"
php scripts/check_loader.php --key=lax1024
```

### 3.2 启动

```bash
# 同目录放置 .env、public/（上传等）
php webman.phar start
# 或
php webman.phar start -d
```

## 注意

- **扩展必须装在服务器 PHP 上**，不能塞进 PHAR。
- 无扩展时加载密文会编译失败，属预期。
- PHP 8.2+ 扩展须使用 `ZEND_COMPILE_POSITION_AT_OPEN_TAG`（v1.0.1 已修复 `unexpected "<"`）。
- 更新扩展后需重新 `make install` 并重启 PHP / Workerman。
- 这是免费自研方案，可抬高阅读源码成本，**不能**等同商业加固（ionCube 等）。
- 密钥勿提交到 Git；生产用环境注入或受控 `php.ini`。
