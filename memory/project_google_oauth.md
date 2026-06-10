---
name: project-google-oauth
description: Configuration Google OAuth pour TestiApp — client ID enregistré
metadata:
  type: project
---

Google Client ID configuré dans `.env` :
`78564751626-m3e7ll7olaj58oce4id71tmrc2q6lkus.apps.googleusercontent.com`

**Why:** L'app mobile Flutter utilise Google Sign-In. Le backend vérifie les ID tokens via `https://oauth2.googleapis.com/tokeninfo`.

**How to apply:** Si le client ID doit être mis à jour (ex: nouveau projet GCP), modifier `GOOGLE_CLIENT_ID` dans `.env` et vider le cache config.
