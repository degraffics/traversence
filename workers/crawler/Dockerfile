# Traversence guide-crawl worker (decisions/0044). The standard library, and pypdf to read PDFs' text (decisions/0157 §7).
FROM python:3.12-slim
WORKDIR /app
RUN apt-get update && apt-get install -y --no-install-recommends osmium-tool && rm -rf /var/lib/apt/lists/*
RUN pip install --no-cache-dir pypdf==5.1.0 duckdb==1.4.1
COPY crawler.py indexer.py .
# Two services from this image (Railway): the crawler (cron, railway.json) and W0 Indexer (always on, railway.indexer.json)
CMD ["python", "-u", "crawler.py"]
