# FreeCRM — frontend Next.js (image de DÉVELOPPEMENT).
#
# Le code est bind-mounté depuis l'hôte (./frontend:/app) et `node_modules`
# est porté par un volume anonyme : cette image n'embarque donc volontairement
# ni le code applicatif ni un build de production. Une image multi-stage
# (next build / next start) sera introduite le jour où un déploiement
# est nécessaire.
FROM node:22-alpine

WORKDIR /app

# Dépendances installées en couche séparée pour profiter du cache Docker.
COPY package.json package-lock.json ./
RUN npm ci

# Le reste du code arrive par le bind mount défini dans docker-compose.yml.

# NODE_ENV n'est PAS fixé ici volontairement : "next dev" force déjà "development" en interne,
# et un NODE_ENV=development explicite dans l'environnement casse "next build" exécuté via
# `docker compose exec frontend npm run build` (bug connu Next.js 16 :
# https://github.com/vercel/next.js/issues/97046 — le prerender de la page interne
# auto-générée "/_global-error" plante avec "Cannot read properties of null (reading 'useContext')"
# dès que NODE_ENV vaut "development" au moment du build).
ENV NEXT_TELEMETRY_DISABLED=1
# Nécessaire pour que le serveur soit joignable depuis l'hôte.
ENV HOSTNAME=0.0.0.0

EXPOSE 3000

CMD ["npm", "run", "dev", "--", "--hostname", "0.0.0.0", "--port", "3000"]
