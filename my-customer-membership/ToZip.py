import zipfile
from pathlib import Path
from datetime import datetime

# ====== 配置区（按需修改） ======
SOURCE_DIR = Path.home() / "cursor_projects"     # Cursor 保存的目录
BACKUP_DIR = Path.home() / "cursor_backups"      # zip 输出目录
# ===============================

def zip_directory(source_dir: Path, backup_dir: Path):
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    zip_path = backup_dir / f"cursor_backup_{timestamp}.zip"

    backup_dir.mkdir(parents=True, exist_ok=True)

    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zipf:
        for file in source_dir.rglob("*"):
            if file.is_file():
                zipf.write(file, file.relative_to(source_dir))

    print(f"✅ Cursor 文件已打包完成：{zip_path}")

if __name__ == "__main__":
    if not SOURCE_DIR.exists():
        raise FileNotFoundError(f"源目录不存在: {SOURCE_DIR}")
    zip_directory(SOURCE_DIR, BACKUP_DIR)
