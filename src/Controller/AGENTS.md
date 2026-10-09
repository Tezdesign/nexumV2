# Training (formations, quizzes, certificates)

## Overview

The training module sits as flat files in `src/Controller/` (not in a sub folder like the other modules). Admins publish formations with three videos and quiz questions. Users follow a formation, reach 90% progress, take the quiz, and a passing score (60%) produces a PDF certificate with a QR code that the user downloads.

## Key files

| File | Owns |
|---|---|
| `FormationController.php` | `/formation`: user list and detail page, progress and location updates, quiz submission (grading, certificate), admin list, create, edit, delete, XLSX export, results, translation, JSON list |
| `QuizController.php` | `/quiz`: question create, edit, delete, the PDF to quiz AI generator and its save step, AI image generation |
| `RatingController.php` | `/formation/{id}/rate`: 1 to 5 stars on a formation |
| `AdminControllerFormation.php` | `/admin/training-stats` and the three chart JSON routes |
| `../Entity/` `Formation`, `Quiz`, `Participer`, `Resultat`, `Rating` | `Participer` is one user's progress in one formation (`progression`, `statut` `PARTICIPER`, `PRET_QUIZ`, `REUSSI`, `ECHEC`, `localisation`). `Resultat` is one quiz attempt. |
| `../Service/CertificateService.php`, `QrService.php` | Certificate PDF (dompdf), QR PNG |
| `../Service/PythonQuizGeneratorService.php`, `QuizImageGenerator.php` | PDF to quiz through a local Python script, and image generation through Hugging Face (`HF_TOKEN`) |
| `../Service/TranslatorService.php`, `BadWordService.php` | MyMemory translation API (cached 30 days per text and language, pieces sent in parallel, a failure is never cached; only fr, en, ar, es, de), profanity cleaning of titles and descriptions |
| `../../templates/formation/`, `quiz/`, `resultat/`, `certificate/` | Views |

## Conventions

- Progress comes from the three videos. The browser reports a finished video and the server awards the next milestone only (33, 66, then 90), so one request can never jump to the end. At 90 the status becomes `PRET_QUIZ`.
- The quiz opens only for `PRET_QUIZ` (first attempt), `ECHEC` (retry) and `REUSSI` (ask for the certificate again). It allows 3 scored attempts per formation per 24 hours, then answers 429. Grading is done on the server: the browser posts `{formationId, answers: {quizId: choice}}` to `/formation/quiz/submit` and the server compares with `Quiz::getCorrect()`. Pass mark is 60%. Questions without a correct choice are not graded. Responses never carry file paths or exception text; the details go to the log.
- Access: `FormationController` and `RatingController` need a login (`#[RequireLogin]` on the class). Everything that changes content is `#[RequireAdmin]`: formation create, edit, delete, admin list, admin quiz page and export, all of `QuizController`, and all of `AdminControllerFormation`. `TrainingAccessTest` checks every one of these routes through the real kernel, so add new admin routes to its provider. Quiz edit and delete check a CSRF token (`edit_quiz_<id>`, `delete_quiz_<id>`). The results page shows an admin everything and anyone else only their own attempts.
- Public upload folders: `public/uploads/videos` (formation videos), `public/uploads/quiz` (question images), (nothing cleans them). Certificates and QR codes are private: `var/certificates/cert_<userId>_<formationId>.pdf` (one file per user and formation, replaced on each new certificate) and `var/qr/` (a QR image lives only while the PDF is drawn). A user downloads their own certificate at `/formation/{id}/certificate` (only after `REUSSI`; it is rebuilt if the file is gone).
- Ratings: one per user and formation (`rating.user_id`, unique with `formation_id`). Rating again replaces the note. Users rate from their formation page, admins from the admin page, and each is sent back to their own page.

## Gotchas

- The description is translated only when the user picks a language on the formation page (`/formation/translate/{id}?lang=`), never while the page loads.
- Tests for this module (`TrainingProgressAndQuizTest`) run on in memory SQLite. They need a repository factory because the app's repositories are built from a `ManagerRegistry`; copy the setup from that test.
- Video uploads go to the container parameter `videos_directory` (`config/services.yaml`, `public/uploads/videos`), used by both create and edit.
- The AI quiz generator is configured with `QUIZ_PYTHON_BIN` (interpreter path, or a name on the PATH; default `python`) and `QUIZ_GENERATOR_SCRIPT` (path to `generate_quiz.py`) in `.env.local`. Without them it still finds the old `pfe_ferdawes_linedata_fst` virtualenv (Windows or Unix) or `python/generate_quiz.py` if present. That script is not in the repository, so the feature stays unavailable until you provide one; the error says which variable to set. The uploaded PDF is kept in the private `var/quiz_ai/` only while the script reads it and is deleted afterwards.
- The certificate id is `CERT-` plus part of `md5(userId|formationId)` and the QR holds plain text with no verification page, so a certificate proves nothing by itself.
- `endroid/qr-code` is 5.x here (fluent `Builder::create()`). The 6.x constructor with named arguments throws, which is why QR codes used to be silently missing.

_Drafted by /audit from the repo, worth a quick human pass. Edit freely: once a line stops matching this draft, later runs treat it as curated and will flag rather than overwrite it._
