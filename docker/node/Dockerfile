# Node.js development environment for React widget
FROM node:18-alpine

# Install development dependencies
RUN apk add --no-cache \
    git \
    python3 \
    make \
    g++ \
    curl \
    bash

# Set working directory
WORKDIR /app

# Create non-root user for security
RUN addgroup -g 1001 -S nodejs && \
    adduser -S reactdev -u 1001

# Install global packages
RUN npm install -g \
    webpack \
    webpack-cli \
    webpack-dev-server \
    jest \
    eslint \
    prettier

# Copy package files first for better caching
COPY widget-src/package*.json ./

# Install dependencies
RUN npm ci --only=production=false

# Set ownership
RUN chown -R reactdev:nodejs /app

# Switch to non-root user
USER reactdev

# Expose ports
EXPOSE 3000 3001

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:3000/health || exit 1

# Default command for development
CMD ["npm", "run", "watch"]