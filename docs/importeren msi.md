# MSI importeren

Voor de kaartlaag MSI is er een locatietabel nodig. De bron hiervoor is een shapefile dat kan worden gedownload bij NDW. Dit moet met de hand worden omgezet naar een CSV bestand, welk vervolgens kan worden ingelezen in de database.

Een voorbeeld van het omgezette CSV bestand is aanwezig in content/msi.csv. Dit raakt vanzelfsprekend verouderd en updates worden niet frequent in dit repository voorzien.

## MSI shape exporteren naar CSV met QGIS
Download shapefile van http://opendata.ndw.nu en pak uit
Open Shapefile in QGIS:
Kaartlagen > Laag toevoegen > Vectorlaag toevoegen
In Paneel Lagen, rechtsklik op kaartlaag en kies Opslaan als
Kies Formaat: Komma gescheiden waarden [CSV]
Kies opslaglocatie: content/msi.csv
Stel onder Laagopties GEOMETRY in op AS_XY
Laat de rest van de instellingen voor wat het is en klik OK.

## CSV bestand inlezen
Voor het inlezen van het CSV bestand wordt het script import_msi.php gebruikt. Voer dit uit vanaf de opdrachtregel.
MySQL/MariaDB moet geconfigureerd zijn om LOAD DATA LOCAL INFILE opdrachten toe te staan.
