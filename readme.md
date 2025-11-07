# ndwkaart - matrixbordenkaart

Dit is tooling en een kaart voor het weergeven van verschillende open datastromen uit het Nationaal Dataportaal Wegverkeer (opendata.ndw.nu). Op dit moment is er ondersteuning voor rijstrooksignalering (matrixborden), dynamische routeinformatiepanelen en incidenten.

# Wat heb je nodig

Een webserver met een recentige versie van PHP en een MySQL of MariaDB database. De mogelijkheid voor cronjobs zijn niet vereist, het kan ook draaien op simpele shared hosting.

# Algemene werking

Het geheel bestaat uit een frontend die wordt aangeroepen vanuit een browser en een backend op de server. De frontend roept de serverscripts aan en zorgt er ook voor dat data periodiek van de NDW open data server wordt opgehaald en weergegeven. Als de frontend niet actief open staat in een browser wordt er ook geen data opgehaald door de backend. De backend staat dan als het ware stand-by en gaat weer aan de slag zodra de webpagina wordt aangeroepen.

# Documentatie

Zie de map docs/ voor meer informatie.

# Broncode

De broncode is beschikbaar op GitHub.

# Licentie

De broncode is beschikbaar onder de voorwaarden van de GNU GPL versie 3 of hoger.
Voor bibliotheken in de map lib/ kunnen andere voorwaarden van toepassing zijn. Zie hiervoor het bestand met licentieinformatie in de submap van elke bibliotheek.

	ndwkaart - matrixbordenkaart
	Copyright (C) 2018, 2025 Jasper Vries

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.
