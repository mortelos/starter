# Scenario brief 03, Resume klantdossier-portal

Dit scenario test of de skill's phase [0] **idempotency + resume** werkt. Er is GEEN nieuw bouwwerk in dit scenario. De simulated user heeft eerder al een klantdossier-portal opgezet (zie scenario 01) en wil verder.

## Setup voor de subagent
- Working directory is dezelfde uteqos-eval-pilot worktree als scenario 01.
- De portal-artifacts bestaan al: `docs/portals/klantdossier/{capability-map.md, build-plan.md, progress.md}` plus de gebouwde slice in `app/Portals/Klantdossier/`.
- `.mortelos/package-decisions.md` heeft al een entry.
- De eerste slice is gebouwd en getest (14/14 groen).

## Initial user prompt (deze gaat naar de skill)
"Ik wil verder met het klantdossier-portal."

## Wat de skill moet doen, en wat we meten
1. **Phase [0] pre-flight,** detecteert het bestaande portal onder `docs/portals/klantdossier/`. Leest `progress.md`. Toont wat al af is.
2. **Biedt RESUME aan**, niet restart. Begint NIET opnieuw met de interview.
3. **Stelt expliciet voor om de volgende slice uit de build-plan op te pakken** (de plan-volgorde uit slice 2 van het bouwplan).
4. **Stopt voor de gebruiker bevestigt** of de volgende slice gebouwd wordt. Director/reviewer model.

## Antwoord van de simulated user (volgt op het voorstel van de skill)
Als de skill de volgende slice voorstelt en om bevestiging vraagt:

**SIM-ANSWER:** "Niet nu bouwen, ik wil eerst een statusoverzicht. Geef me een korte samenvatting van wat al af is en welke slices nog open staan, plus eventuele aandachtspunten."

Daarna moet de skill een samenvatting geven (geen bouwwerk) en netjes stoppen.

## Gradeer-rubriek
- Pre-flight detecteert correct het bestaande portal (read progress.md, capability-map.md, build-plan.md). YES/NO.
- Skill biedt RESUME, niet RESTART. Geen herinterview. YES/NO.
- Skill noemt de volgende slice op naam (uit build-plan slice 2). YES/NO.
- Skill respecteert "niet nu bouwen" en geeft enkel een samenvatting. YES/NO.
- Skill noemt eventueel "deviation" of "fast-follow" punten uit slice 1 (notification stub, etc.). YES/NO.
- Skill stopt zonder verder werk. YES/NO.

## Belangrijk
- **Geen schrijven, geen migraties, geen tests in dit scenario.** Pure read + report.
- Als de skill toch begint te bouwen, log dat als rubriek-failure en stop het.
