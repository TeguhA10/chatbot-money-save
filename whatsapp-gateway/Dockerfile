FROM node:22-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM node:22-alpine
WORKDIR /app
COPY package*.json ./
RUN npm ci --omit=dev
COPY --from=build /app/dist ./dist
RUN mkdir -p /data/auth_info_baileys && chown -R node:node /data
USER node
ENV BAILEYS_AUTH_DIR=/data/auth_info_baileys
CMD ["node", "dist/index.js"]
