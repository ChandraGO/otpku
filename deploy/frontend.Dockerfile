# syntax=docker/dockerfile:1.7
FROM node:20-alpine AS build

WORKDIR /app

# Mempercepat production build. Source map tidak dibutuhkan di browser production,
# dan lint tidak perlu dijalankan ulang di dalam image setiap deploy kecil.
ENV GENERATE_SOURCEMAP=false \
    DISABLE_ESLINT_PLUGIN=true

# Dependency menjadi layer terpisah. Selama package.json/yarn.lock tidak berubah,
# langkah yarn install akan langsung CACHED.
COPY frontend/package.json frontend/yarn.lock ./
RUN --mount=type=cache,target=/usr/local/share/.cache/yarn \
    yarn install --frozen-lockfile --prefer-offline

COPY frontend/ ./

ARG REACT_APP_BACKEND_URL=https://dapetotp.jagoanproject.com
ENV REACT_APP_BACKEND_URL=${REACT_APP_BACKEND_URL}

# Cache webpack/CRACO dipertahankan antar build BuildKit.
RUN --mount=type=cache,target=/app/node_modules/.cache \
    yarn build

FROM nginx:1.27-alpine

COPY deploy/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/build /usr/share/nginx/html

EXPOSE 80
