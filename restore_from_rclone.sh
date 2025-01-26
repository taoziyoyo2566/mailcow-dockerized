#!/usr/bin/env bash
#
# Mailcow Dockerized Offline Restore from Rclone
# - 拉取备份文件 (包含 .tar, mailcow-data.tar.gz, sha256sums.txt 等)
# - 校验完整性 (sha256sums.txt)
# - 停止容器，解包还原
# - 重启服务
#
# v1.0

set -euo pipefail

####################################
# 1. 基础变量 (请根据实际情况修改)
####################################

# Mailcow 安装目录
MAILCOW_DIR="/opt/docker/mailcow-dockerized"

# 恢复使用的临时目录
RESTORE_TMP="$MAILCOW_DIR/restore_tmp"

# 指定 Rclone config 路径 (如果你在备份脚本中也使用了同样的做法)
export RCLONE_CONFIG="/home/spartan/.config/rclone/rclone.conf"

# Rclone 远程名称和目录，需要与备份脚本一致
REMOTE_NAME="mailcow"
REMOTE_DIR="mailcow-backups"

# 需要还原的卷列表 (和备份时一致)
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

####################################
# 2. 用户输入或选择要恢复的日期文件夹
####################################
# 你可以让用户手动指定某次备份日期 (例如 2025-01-27-0109) 或列出远程备份目录让用户选择。
# 这里简单写成从脚本参数读取:
if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <BACKUP_DATE_FOLDER>"
  echo "Example: $0 2025-01-27-0109"
  exit 1
fi

BACKUP_DATE="$1"
REMOTE_BACKUP_PATH="${REMOTE_NAME}:${REMOTE_DIR}/${BACKUP_DATE}"

echo "[+] You have chosen backup folder: $REMOTE_BACKUP_PATH"

####################################
# 3. 从 Rclone 远程下载到本地
####################################

# 创建临时目录
mkdir -p "$RESTORE_TMP/$BACKUP_DATE"

# 拉取远程的 .tar, mailcow-data.tar.gz, sha256sums.txt 等
# 建议加 -v --progress 观察进度
echo "[+] Downloading backup from $REMOTE_BACKUP_PATH ..."
rclone copy "$REMOTE_BACKUP_PATH" "$RESTORE_TMP/$BACKUP_DATE" -v --progress

cd "$RESTORE_TMP/$BACKUP_DATE"

####################################
# 4. 校验完整性 (可选)
####################################
if [[ -f "sha256sums.txt" ]]; then
  echo "[+] Checking file integrity with sha256sums.txt..."
  # 如果一切正常，会显示 OK；若不匹配则报错并退出
  sha256sum -c sha256sums.txt
  echo "[+] Integrity check passed."
else
  echo "[-] Warning: sha256sums.txt not found. Skipping checksum verification."
fi

####################################
# 5. 停止容器 (离线恢复)
####################################
echo "[+] Stopping Mailcow stack..."
cd "$MAILCOW_DIR"
docker compose down

####################################
# 6. 还原 Docker Volumes
####################################
echo "[+] Restoring Docker volumes..."

for V in "${VOLUMES[@]}"; do
  ARCHIVE_FILE="$RESTORE_TMP/$BACKUP_DATE/${V}.tar"
  if [[ -f "$ARCHIVE_FILE" ]]; then
    echo "  - Restoring volume: $V"
    docker run --rm \
      -v "$V":/data \
      -v "$RESTORE_TMP/$BACKUP_DATE":/backup \
      alpine \
      sh -c "cd /data && tar xf /backup/${V}.tar"
  else
    echo "  - Skipping $V: Archive file not found: ${V}.tar"
  fi
done

####################################
# 7. 还原 ./data 目录 (带 sudo)
####################################
DATA_ARCHIVE="$RESTORE_TMP/$BACKUP_DATE/mailcow-data.tar.gz"
if [[ -f "$DATA_ARCHIVE" ]]; then
  echo "[+] Restoring mailcow-data.tar.gz..."
  # 使用sudo解压，以还原正确的权限
  sudo tar -xzf "$DATA_ARCHIVE" -C "$MAILCOW_DIR"
else
  echo "[-] Warning: mailcow-data.tar.gz not found. Skipping data directory restore."
fi

####################################
# 8. 重启 Mailcow
####################################
echo "[+] Restarting Mailcow stack..."
docker compose up -d

####################################
# 9. 验证
####################################
echo "[+] Restore completed at $(date). Please verify your Mailcow instance."
echo "    Restoration used files in $RESTORE_TMP/$BACKUP_DATE"
