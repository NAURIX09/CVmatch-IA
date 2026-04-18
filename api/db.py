"""
db.py — Pool de connexions MySQL pour l'API Python
"""
import os
import pymysql
from dbutils.pooled_db import PooledDB
from dotenv import load_dotenv

load_dotenv()

_pool = PooledDB(
    creator=pymysql,
    maxconnections=10,
    mincached=2,
    host=os.getenv("DB_HOST", "localhost"),
    port=int(os.getenv("DB_PORT", 3306)),
    user=os.getenv("DB_USER", "root"),
    password=os.getenv("DB_PASS", ""),
    database=os.getenv("DB_NAME", "cvmatch_ia"),
    charset="utf8mb4",
    cursorclass=pymysql.cursors.DictCursor,
)


def get_conn():
    """Retourne une connexion depuis le pool. Toujours appeler conn.close() après."""
    return _pool.connection()
