import mysql.connector
from mysql.connector import pooling
from contextlib import contextmanager
from .config import DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME

# Create a connection pool for FastAPI
pool = pooling.MySQLConnectionPool(
    pool_name="hostel_pool",
    pool_size=10,
    pool_reset_session=True,
    host=DB_HOST,
    port=DB_PORT,
    user=DB_USER,
    password=DB_PASS,
    database=DB_NAME,
    autocommit=True
)

@contextmanager
def get_db():
    """Context manager for obtaining a database connection from the pool."""
    connection = pool.get_connection()
    try:
        cursor = connection.cursor(dictionary=True)
        yield cursor, connection
    finally:
        cursor.close()
        connection.close()

def execute_query(query: str, params: tuple = None, fetch_one: bool = False, fetch_all: bool = False):
    """Utility helper to quickly run queries and fetch records."""
    with get_db() as (cursor, conn):
        cursor.execute(query, params or ())
        if fetch_one:
            return cursor.fetchone()
        if fetch_all:
            return cursor.fetchall()
        return cursor.lastrowid
