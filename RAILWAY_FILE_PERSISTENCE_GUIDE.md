# 📁 Gabay sa Persistent File Storage sa Railway (Bakit Nawawala ang Uploaded Files at Paano Ito Ayusin)

> **📌 Layunin ng Dokumento:**  
> Ipaliwanag kung bakit nawawala ang mga in-upload na lecture modules (`.pdf`, `.ppt`, `.pptx`, `.doc`, `.docx`) sa tuwing nagre-restart, nagde-deploy, o sumasara ang Railway container, at magbigay ng malinaw na step-by-step solution para maging permanente ang mga ito.

---

## 🔍 1. Root Cause Analysis: Bakit Nawawala ang Files Pagkatapos Magsara ng Railway?

### Ang Pagkakaiba ng Database vs Container Filesystem:

1. **Bakit nananatili ang Database data?**
   - Ang ating `MySQL` service sa Railway ay may nakakabit na **`mysql-volume`** (makikita sa Railway canvas). Dahil may hiwalay na persistent volume disk ang MySQL, hindi nawawala ang accounts, quiz attempts, at records.
   
2. **Bakit nawawala ang mga Files (PDF, PPT, Word)?**
   - Ang application service natin (`revv2`) ay tumatakbo sa loob ng isang **Docker Container**.
   - Ang default na filesystem ng Docker container sa Railway ay **Ephemeral (Pansamantala)**:
     - Sa tuwing mag-push ka sa GitHub, mag-redeploy sa Railway, o mag-restart ang server, **sinisira ng Railway ang lumang container** at gumagawa ng **bagong-bagong container** mula sa Docker image.
     - Ang mga in-upload na files na na-save sa `/var/www/html/storage/app/public` sa loob ng lumang container ay **nabubura kasama ng lumang container**.

---

## 📂 2. Kasalukuyang File Storage Architecture sa Codebase

Sa ating Laravel backend:
* **Controller:** `app/Http/Controllers/ClassManagerController.php`
* **Upload Mechanism:**
  ```php
  // Single document upload:
  $filePath = $file->storeAs("modules/{$class->id}", $uniqueName, 'public');
  
  // Lecture subpart uploads (PPT, PDF, DOCX):
  $filePath = $file->storeAs("modules/{$module->id}/subparts", $uniqueName, 'public');
  ```
* **Pisikal na Lokasyon sa Server:** `/var/www/html/storage/app/public/modules/...`
* **Public Web Link:** Naa-access ng estudyante sa pamamagitan ng symlink sa `/var/www/html/public/storage` na awtomatikong ginagawa ng ating `docker/entrypoint.sh` via `php artisan storage:link`.

Dahil lokal na disk (`public` disk) ang ginagamit ng Laravel, kailangan natin ng paraan upang ang folder na ito ay mai-mount sa isang **Persistent Volume** sa Railway.

---

## 🛠️ 3. Solusyon 1 (Inirerekomenda): Magkabit ng Railway Persistent Volume

Ito ang pinakamabilis, pinakamadali, at **hindi nangangailangan ng anumang pagbabago sa code**.

### 📋 Mga Hakbang sa Railway Dashboard:

1. **Buksan ang Project Canvas sa Railway:**
   - Mag-log in sa [railway.app](https://railway.app) at buksan ang iyong project.

2. **Mag-dagdag ng Volume sa `revv2`:**
   - I-click ang **`revv2`** service box sa canvas.
   - Pumunta sa tab na **`Settings`**.
   - Mag-scroll pababa sa seksyong **`Volumes`**.
   - Pindutin ang **`+ Add Volume`** (o **`Attach Volume`**).

3. **I-configure ang Mount Path:**
   - Ilagay ang eksaktong Mount Path:
     ```text
     /var/www/html/storage/app/public
     ```
   - *Paliwanag:* Ito ang eksaktong folder kung saan isinusulat ng Laravel ang lahat ng in-upload na lecture modules, subpart files, at attachments.

4. **I-save at Mag-redeploy:**
   - Pindutin ang **Add** o **Save**.
   - Awtomatikong magre-restart ang `revv2` na may nakakabit nang permanenteng disk (`volume`).
   - Makakakita ka ng maliit na drive icon sa ilalim ng `revv2` (tulad ng `mysql-volume` sa ilalim ng MySQL).

> ✅ **Resulta:** Lahat ng PPT, PDF, at Word files na i-a-upload ng mga guro mula rito ay mai-save sa dedicated volume. Kahit ilang beses mag-redeploy, mag-push ng code, o magsara ang server, **100% mananatili at hindi na mabubura ang mga files**.

---

## ☁️ 4. Solusyon 2 (Alternatibo para sa Production Scale): Cloudflare R2 / AWS S3

Kung ayaw gumamit ng Railway disk volume o nais ng cloud-scale object storage na may 10GB libreng tier at zero egress fees:

1. **Gumawa ng Bucket sa Cloudflare R2:**
   - Mag-sign up sa Cloudflare Dashboard $\rightarrow$ **R2 Storage** $\rightarrow$ **Create Bucket** (e.g. `reviso-modules`).
   - Gumawa ng API Tokens na may `Object Read & Write` access.

2. **I-configure ang Variables sa Railway:**
   Idagdag ang mga sumusunod sa **Variables** tab ng `revv2`:
   ```env
   FILESYSTEM_DISK=s3
   AWS_ACCESS_KEY_ID=iyong_r2_access_key
   AWS_SECRET_ACCESS_KEY=iyong_r2_secret_key
   AWS_DEFAULT_REGION=auto
   AWS_BUCKET=reviso-modules
   AWS_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
   AWS_USE_PATH_STYLE_ENDPOINT=true
   ```

3. Kapag naka-set ito, diretso nang aakyat sa Cloudflare R2 ang mga in-upload na modules.

---

## 🧪 5. Testing & Verification Checklist

Pagkatapos ikabit ang Railway Volume sa Step 3:

1. **Mag-login bilang Teacher:**
   - Email: `teacher.psych@reviso.com`
   - Password: `teacher123`
2. **Mag-upload ng Subok na Module:**
   - Pumunta sa Teacher Dashboard $\rightarrow$ Pumili ng klase $\rightarrow$ Mag-upload ng bagong lecture kasama ang `.pdf` o `.pptx` file.
3. **I-test ang Persistence:**
   - Pumunta sa Railway $\rightarrow$ I-click ang **`Redeploy`** sa `revv2` para piliting mag-restart ang container.
4. **I-verify:**
   - Pagkatapos mag-restart, buksan ang lecture module.
   - I-click ang download o view link ng file.
   - Kung naida-download o nabubuksan pa rin ang PDF/PPT, **permanente at ligtas na ang storage!**
