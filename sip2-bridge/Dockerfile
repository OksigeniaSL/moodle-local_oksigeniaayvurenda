# The bridge, with nothing but Python. Its settings come in at run time, and hold the addresses of the posts: keep them private.
FROM python:3.12-alpine
COPY bridge.py /app/bridge.py
USER nobody
EXPOSE 6001
ENTRYPOINT ["python", "-I", "-B", "/app/bridge.py"]
CMD ["/etc/sip2-bridge/config.json"]
