TEMAN / THEMES
==============
Alla .css-filer i den här mappen och i mappen custom/ visas automatiskt som val under Min profil > Tema.
Standardtemat (utan fil) är sidans ursprungliga färger och används för nya användare.
Every .css file in this folder and in custom/ is listed automatically under My profile > Theme.
The default theme (no file) is the original look and is used for new users.

SKAPA ETT EGET TEMA / MAKE YOUR OWN
1. Kopiera ett befintligt tema, t.ex. nord.css, till custom/mitt-tema.css (filnamn: a-z, 0-9, - och _).
   Copy an existing theme, e.g. nord.css, to custom/my-theme.css (file name: a-z, 0-9, - and _).
2. Ändra namnet i kommentaren på första raden: /* name: Mitt tema */  och färgvärdena.
   Change the name in the first-line comment and the colour values.
3. Ladda om sidan och välj temat i profilen. Reload the page and pick the theme in your profile.
   Filer i custom/ skrivs aldrig över vid uppgradering. Files in custom/ are never overwritten by upgrades.

VARIABLER / VARIABLES
 --bg      sidans bakgrund            page background
 --card    kort, tabeller, meny        cards, tables, menu
 --bd      kantlinjer                  borders
 --tx      text                        text
 --mut     dämpad text                 muted text
 --ok --warn --bad --off   status: OK / varning / kritisk / inaktiv
 --acc     accentfärg (länkar, markering) accent colour    --acc2 ljusare accent / lighter accent
 --onacc   text på accentfärg          text on accent colour
 --inp     bakgrund för fält och grafer  input and chart background
 --btn     knappar                     buttons
 --hov     radmarkering vid hover      row hover
 --pill    inaktiva etiketter          inactive pills
 --okbg --okbd / --warnbg --warnbd / --badbg --badbd   bakgrund/kant för status / status background/border
 --infobg --infotx   infoetiketter     info labels
 --mut2    svagare text/kantfärg       fainter text/border
 --bd2     starkare kantlinje          stronger border
 color-scheme: light eller dark (styr rullningslister och formulärfält / controls and scrollbars)

MALL / TEMPLATE
/* name: Mitt tema */
:root{--bg:#101820;--card:#18232f;--bd:#26364a;--tx:#e6edf5;--mut:#8da2b8;--ok:#34d399;--warn:#fbbf24;--bad:#f87171;--off:#64748b;--acc:#60a5fa;
--inp:#0c131b;--btn:#223246;--onacc:#07101f;--hov:#1d2b3b;--pill:#223246;--okbg:#12281f;--okbd:#1d4a38;--warnbg:#2d2410;--warnbd:#5a4a1d;
--badbg:#341a1e;--badbd:#5a2730;--infobg:#13243a;--infotx:#7fb4f5;--mut2:#5d667a;--bd2:#2f3a52;--acc2:#4c9aff;color-scheme:dark}
