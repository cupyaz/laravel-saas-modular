FROM node:18-alpine

WORKDIR /var/www/html

# Install dependencies for native modules
RUN apk add --no-cache python3 make g++

# Copy package files
COPY package*.json ./

# Install Node.js dependencies
RUN npm install

# Install Vite globally for hot reloading
RUN npm install -g vite

# Copy the rest of the application
COPY . .

# Create user matching the host user
RUN addgroup -g 1000 www && adduser -D -u 1000 -G www www

# Change ownership
RUN chown -R www:www /var/www/html

USER www

EXPOSE 3000 5173

CMD ["npm", "run", "dev"]