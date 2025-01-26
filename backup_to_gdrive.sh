#!/usr/bin/env bash
#
# Mailcow Dockerized Offline Backup -> Rclone remote
#  - 使用sudo打包，避免Permission denied
#  - 上传前验证Rclone远程可用性
#  - 生成SHA256校验文件
#
# v2.1

set -euo pipefail

# 强制使用普通用户的 rclone.conf
export RCLONE_CONFIG="/home/spartan/.config/rclone/rclone.conf"

####################################
# 1. 变量定义，可根据需求自行修改
####################################

# Mailcow 安装目录
MAILCOW_DIR="/opt/docker/mailcow-dockerized"

# 临时备份目录
BACKUP_TMP="$MAILCOW_DIR/backup_tmp"

# 时间戳
DATE="$(date +%F-%H%M)"

# Rclone 远程名称（可自定义，比如“mailcow”、“gdrive”等）
# 确保这个名称和你在 `rclone config` 里创建的 remote 同名
REMOTE_NAME="mailcow"
REMOTE_DIR="mailcow-backups"  # 在远程中的目录
REMOTE_PATH="${REMOTE_NAME}:${REMOTE_DIR}/${DATE}"

# Docker Volumes 列表（可根据实际情况进行增减）
VOLUMES=(
  "vmail-vol-1"
  "vmail-index-vol-1"
  "mysql-vol-1"
  "redis-vol-1"
  "rspamd-vol-1"
  "postfix-vol-1"
  "crypt-vol-1"
  "sogo-web-vol-1"
  "sogo-userdata-backup-vol-1"
  "clamd-db-vol-1"
)

#####################################
# 2. 验证 Rclone 远程可用性
#####################################
echo "[+] Checking Rclone remote: $REMOTE_NAME"

# 2.1 确认已经配置过该 remote
if ! rclone listremotes | grep -qx "${REMOTE_NAME}:"; then
  echo "[-] Error: Rclone remote '$REMOTE_NAME' not found in 'rclone listremotes'!" >&2
  echo "    Please run 'rclone config' to configure or correct the remote name."
  exit 1
fi

# 2.2 测试列出远程根目录，检查网络和授权
#    如果能列出(或为空)则说明连接成功；若出错，会返回非0退出码
if ! rclone lsd "${REMOTE_NAME}:" >/dev/null; then
  echo "[-] Error: Unable to access remote '$REMOTE_NAME'. Check network or authorization."
  exit 1
fi

echo "[+] Rclone remote '$REMOTE_NAME' is accessible. Proceeding..."

#####################################
# 3. 停止 Mailcow 容器 (离线备份)
#####################################
echo "[+] Stopping Mailcow stack..."
cd "$MAILCOW_DIR"
docker compose down

mkdir -p "$BACKUP_TMP/$DATE"
echo "[+] Created backup directory: $BACKUP_TMP/$DATE"

#####################################
# 4. 备份 Docker Volumes (离线)
#####################################
echo "[+] Backing up Docker volumes..."
for V in "${VOLUMES[@]}"; do
  echo "  - Backing up volume: $V"
  docker run --rm \
    -v "$V":/data:ro \
    -v "$BACKUP_TMP/$DATE":/backup \
    alpine \
    sh -c "cd /data && tar cf /backup/${V}.tar ."
done

#####################################
# 5. 使用 sudo 打包 ./data 目录
#####################################
echo "[+] Backing up ./data directory with sudo..."
sudo tar -czf "$BACKUP_TMP/$DATE/mailcow-data.tar.gz" -C "$MAILCOW_DIR" data

#####################################
# 6. 重启 Mailcow
#####################################
echo "[+] Restarting Mailcow stack..."
docker compose up -d

#####################################
# 7. 生成校验和 (SHA256)
#####################################
echo "[+] Generating SHA256 checksums..."
cd "$BACKUP_TMP/$DATE"
sha256sum *.tar mailcow-data.tar.gz > sha256sums.txt

#####################################
# 8. 上传到 Rclone 远程
#####################################
echo "[+] Uploading backup to $REMOTE_PATH ..."
# 如果远端目录不存在，则创建
rclone mkdir "${REMOTE_NAME}:${REMOTE_DIR}" >/dev/null 2>&1

# 开启详细日志/进度，可去掉 -v/--progress 改成静默模式
rclone copy "$BACKUP_TMP/$DATE" "$REMOTE_PATH" -v --progress

echo "[+] Backup completed at $(date)."
echo "    Files + sha256sums.txt are now in $REMOTE_PATH"
