# Importeren DRIP

NDW publiceert een locatietabel voor DRIPs die geautomatiseerd geimporteerd kan worden. Voer hiervoor het script import_drip.php uit.

De locatietabel van NDW bevat geen orientatie (draairichting) voor DRIPs en ook geen informatie over de wegbeheerder en het type DRIP. De wegbeheerder is weliswaar nog met enige zekerheid af te leiden uit het ID en/of de naam van de DRIP. De kaart biedt echter wel ondersteuning voor deze drie aanvullende velden en gebruikt de orientatie bovendien rechtstreeks voor de draaiing van het DRIP-pictogram op de kaart.

Het importscript biedt ondersteuning om deze aanvullende informatie op te halen uit de assetwebsite. Een uittreksel hiervan is opgenomen in de map voorbeeldfiles/. Als je bekend bent met de assetwebsite, dan kun je vanuit sources.cfg.php hier rechtstreeks naar linken.

De koppeling tussen de NDW locatietabel en de assetwebsite gebeurt op basis van coordinaten. Voor iedere DRIP in de NDW locatietabel wordt de dichtstbijzijnde DRIP in de assetwebsitetabel gezocht. Wanneer deze maximaal 50 meter uit elkaar liggen, dan wordt aangenomen dat het dezelfde DRIP betreft en dan wordt de naam, wegbeheerder, locatie, orientatie en type DRIP overgenomen uit de assetwebsitetabel. Indien er geen DRIP binnen 50 meter gevonden wordt, dan wordt enkel de informatie uit de NDW locatietabel gebruikt en heeft de DRIP bij gevolg ook geen draairichting op de kaart.
