# Traversence guide-crawl worker (decisions/0044). The standard library, and pypdf to read PDFs' text (decisions/0157 §7).
FROM python:3.12-slim
WORKDIR /app
RUN pip install --no-cache-dir pypdf==5.1.0
COPY crawler.py .
CMD ["python", "-u", "crawler.py"]
