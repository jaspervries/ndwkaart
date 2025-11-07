# Installatie

Voer het script install.php uit voor de initiele installatie. Dit kan worden aangeroepen vanuit een browser, hoewel het daar niet echt voor gemaakt is.

De eerste keer wordt een bestand config.cfg.php aangemaakt. Pas dit aan om de inloginformatie voor de database op te geven. Voer daarna install.php opnieuw uit om alle tabellen en de noodzakelijke mappenstructuur aan te maken.

Voer daarna afzonderlijk de scripts import_drip.php en import_msi.php uit. Deze moeten vanuit de opdrachtregel worden uitgevoerd. In geval van shared hosting kun je eerst alles lokaal testen en daarna de databasetabellen overzetten. In theorie zou je de scripts na kleine aanpassing ook vanuit de browser kunnen uitvoeren, maar dit is niet getest.
De twee importscripts maken de locatietabellen aan voor DRIPs en signaalgevers. De broninformatie wordt rechtstreeks van internet opgehaald. Mocht dit problemen geven, dan zijn er (oude) snapshots aanwezig in de map voorbeeldfiles/. Deze kun je gebruiken door de verwijzing(en) in sources.cfg.php aan te passen.

Na afronding van installatie kun je de hoofdpagina openen in de browser en zou alles moeten werken.
