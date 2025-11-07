# traveltime

Er is nog wat oude code aanwezig met betrekking tot reistijdtrajecten. Dit is nooit werkend geweest.
De bestanden import_traveltime.php en content/traveltime.kml hebben daarom nu geen functie. In map.js is nog wat uitgecommentarieerde code aanwezig ten behoeve van een laag met reistijden. Misschien wordt het ooit nog wat.

Hieronder is nog wat uitleg hoe de shapefile met meetvakken m.b.v. QGIS kan worden omgezet naar KML.

Meetvakken shape exporteren naar KML
Download shapefile van http://opendata.ndw.nu en pak uit
Open Shapefile in QGIS:
Kaartlagen > Laag toevoegen > Vectorlaag toevoegen
In Paneel Lagen, rechtsklik op kaartlaag en kies Opslaan als
Kies Formaat: Keyhole Markup Language [KML]
Kies opslaglocatie: content/traveltime.kml
Selecteer om alle velden te exporteren middels de knop "Alles selecteren"
Scrol naar beneden naar Databron Opties en vul achter NameField in: dgl_loc
Laat de rest van de instellingen voor wat het is en klik OK.