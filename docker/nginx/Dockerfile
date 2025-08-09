FROM nginx:alpine

# Copy configuration files
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# Create SSL directory and generate self-signed certificate for development
RUN mkdir -p /etc/nginx/ssl && \
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/nginx/ssl/selfsigned.key \
    -out /etc/nginx/ssl/selfsigned.crt \
    -subj "/C=US/ST=State/L=City/O=Organization/CN=localhost"

# Create directory for logs
RUN mkdir -p /var/log/nginx

EXPOSE 80 443