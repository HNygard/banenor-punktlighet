# BaneNor - Punktlighet

Utregninger basert på innsynshendvendelse til BaneNor:

- https://www.mimesbronn.no/request/togavganger_og_ankomst_pa_stavan

## CSV-fil

LibreOffice Calc - save as CSV:
- UTF-8
- Field delimiter: ,
- Text delimiter: "
- "Save cell content as shown"

## Informasjon fra BaneNor (ark i regnearket)

Info om utvalget av data fra TIOS database, per 12.10.2017 for perioden 01.01.-30.09.2017			
			
			
J.Skjæret (Bane NOR) og Jon .E. Edvardsen (Ciber AS)			
 			
 			
Planlagt- og faktisk togtrafikk på én gitt stasjon			
 			
Utvalg:			
* Stasjon: Stavanger (kode = STV)			
* Datointervall:  f.o.m. 01.01.2017 t.o.m. 30.09.2017			
* Togslag: Persontog (kode = Pt) og Ekstra persontog (kode = EPt)			
 			
 			
Resultat i tabellform:			
* Stasjonsnavn			
*  Utgangsdato			
*   Tognr			
*   Utgangstasjon			
*  Endestasjon			
*    Innstilt tog (*)			
*  Delinnstilt Stavanger (**)			
*    Togslag (Pt eller EPt)			
*   Togtype (Lokaltog, Regiontog, Nattog etc)			
*  Linjenr (Hvis det finnes angitt på dette tognr)			
*  Toglengde			
*   Lok-/motorvogntype (EL18, 71, 73, 75 etc)			
	(Dersom «Loktype» i vognopptak inneholder data, bruk den. Hvis ikke bruk de to første siffere i VognID.)		
	(Benytter den siste (høyeste nummererte) vognopptakversjon.)		
*   Planlagt spornr			
*   Faktisk spornr			
*   Planlagt ankomsttid			
*  Faktisk ankomsttid			
*    Planlagt avgangstid			
*  Faktisk avgangstid			
*   Stasjonsbruk (Angivelse om toget har Oslo S som utgang- (U), ende- (E) eller gjennomgang (G) – stasjon)			
*  Assosiert tognr (Hvis det finnes angitt på dette tognr)			
			
			
Innstillingskoder:			
			
fotnote,	type innstilling,	innstilltkode	beskrivelse
*	*	Innstilt tog,	Y,	Helinnstilt tog
*	*	Innstilt tog,	P,	Delinnstilt tog
*	*	Innstilt tog,	B,	Buss for tog
*	*	Innstilt tog,	N,	Ikke innstilt tog
*	**	Delinnstilt på stasjon STV,	Y,	Innstilt ved denne stasjonen
*	**	Delinnstilt på stasjon STV,	A,	Ankomst innstilt ved denne stasjonen
*	**	Delinnstilt på stasjon STV,	D,	Avgang innstilt ved denne stasjonen
*	**	Delinnstilt på stasjon STV,	B,	Buss for tog ved denne stasjonen
*	**	Delinnstilt på stasjon STV,	E,	Ankomst av buss for tog er innstilt ved denne stasjonen
*	**	Delinnstilt på stasjon STV,	L,	Avgang av buss for tog er innstilt ved denne stasjonen
*	**	Delinnstilt på stasjon STV,	N,	Ikke innstilt ved denne stasjonen

## SQL-spørring fra BaneNor (ark i regnearket)

    WITH INPUT (stasjon_kd, fra_dato, til_dato, ktg1, ktg2) 
         AS (SELECT 'STV', 
                    To_date('01.01.2017', 'dd.mm.yyyy'), 
                    To_date('30.09.2017', 'dd.mm.yyyy'), 
                    'Pt', 
                    'EPt' 
             FROM   dual), 
         utvalg (stasjon_nv, utg_dt, tog_nr, utgstasjon_kd, endestasjon_kd, innstilt, delinnstilt, tog_ktg, togtype_nv, linjenummer, lengde, lok_ty, vogn_nr, sporfelt_nr, virksporfelt_nr, plansporfelt_nr, planlagt_ankomst, faktisk_ankomst,
         planlagt_avgang, faktisk_avgang, stasjonsbruk, asstog_nr) 
         AS (SELECT (SELECT stasjon.stasjon_nv 
                     FROM   tios.stasjon 
                     WHERE  stasjon.stasjon_kd = ruteplan.stasjon_kd)                                              stasjon_nv,
                    ruteplan.utg_dt, 
                    ruteplan.tog_nr, 
                    toginfo.utgstasjon_kd, 
                    toginfo.endestasjon_kd, 
                    toginfo.innstilt, 
                    ruteplan.delinnstilt, 
                    toginfo.tog_ktg, 
                    (SELECT togtype.togtype_nv 
                     FROM   tios.togtype 
                     WHERE  toginfo.tog_ty = togtype.tog_ty)                                                       togtype_nv,
                    (SELECT linjenummer 
                     FROM   tios.linjenummer 
                     WHERE  linjenummer.id = toginfo.linjenummer_id)                                               linjenummer,
                    (SELECT togkonfig.tog_lgd 
                     FROM   tios.togkonfig 
                     WHERE  togkonfig.utg_dt = ruteplan.utg_dt 
                            AND togkonfig.tog_nr = ruteplan.tog_nr 
                            AND togkonfig.versjon IN (SELECT MAX(vogn_lokomotiv_versjon.versjon) 
                                                      FROM   tios.vogn_lokomotiv_versjon 
                                                      WHERE  vogn_lokomotiv_versjon.utg_dt = ruteplan.utg_dt
                                                             AND vogn_lokomotiv_versjon.tog_nr = ruteplan.tog_nr)) lengde,
                    (SELECT MIN(lokomotiv.lok_ty) 
                     FROM   tios.lokomotiv 
                     WHERE  lokomotiv.utg_dt = ruteplan.utg_dt 
                            AND lokomotiv.tog_nr = ruteplan.tog_nr 
                            AND lokomotiv.versjon IN (SELECT MAX(vogn_lokomotiv_versjon.versjon) 
                                                      FROM   tios.vogn_lokomotiv_versjon 
                                                      WHERE  vogn_lokomotiv_versjon.utg_dt = ruteplan.utg_dt
                                                             AND vogn_lokomotiv_versjon.tog_nr = ruteplan.tog_nr)) LOK_TY,
                    (SELECT Substr(MIN(vogn.vogn_nr), 1, 2) 
                     FROM   tios.vogn 
                     WHERE  vogn.utg_dt = ruteplan.utg_dt 
                            AND vogn.tog_nr = ruteplan.tog_nr 
                            AND vogn.versjon IN (SELECT MAX(vogn_lokomotiv_versjon.versjon) 
                                                 FROM   tios.vogn_lokomotiv_versjon 
                                                 WHERE  vogn_lokomotiv_versjon.utg_dt = ruteplan.utg_dt
                                                        AND vogn_lokomotiv_versjon.tog_nr = ruteplan.tog_nr))      VOGN_NR,
                    ruteplan.sporfelt_nr, 
                    ruteplan.virksporfelt_nr, 
                    ruteplan.planlsporfelt_nr, 
                    ruteplan.sta_tid                                                                               planlagt_ankomst,
                    ruteplan.ata_tid                                                                               faktisk_ankomst,
                    ruteplan.std_tid                                                                               planlagt_avgang,
                    ruteplan.atd_tid                                                                               faktisk_avgang,
                    (SELECT CASE 
                              WHEN ruteplan.stasjon_kd = toginfo.endestasjon_kd THEN 'E' 
                              WHEN ruteplan.stasjon_kd = toginfo.utgstasjon_kd THEN 'U' 
                              ELSE 'G' 
                            END 
                     FROM   dual)                                                                                  stasjonsbruk,
                    (SELECT MIN (asstog_nr) 
                     FROM   (SELECT asstog_nr 
                             FROM   tios.assosiasjon 
                             WHERE  assosiasjon.utg_dt = ruteplan.utg_dt 
                                    AND assosiasjon.tog_nr = ruteplan.tog_nr 
                                    AND assosiasjon.stasjon_kd = ruteplan.stasjon_kd 
                             UNION ALL 
                             SELECT tog_nr 
                             FROM   tios.assosiasjon 
                             WHERE  assosiasjon.assutg_dt = ruteplan.utg_dt 
                                    AND assosiasjon.asstog_nr = ruteplan.tog_nr 
                                    AND assosiasjon.stasjon_kd = ruteplan.stasjon_kd))                             asstog_nr
             FROM   tios.ruteplan, 
                    INPUT, 
                    tios.toginfo 
             WHERE  ruteplan.stasjon_kd = INPUT.stasjon_kd 
                    AND ruteplan.utg_dt >= INPUT.fra_dato 
                    AND ruteplan.utg_dt <= INPUT.til_dato 
                    AND toginfo.tog_nr = ruteplan.tog_nr 
                    AND toginfo.utg_dt = ruteplan.utg_dt 
                    AND ( toginfo.tog_ktg = INPUT.ktg1 
                           OR toginfo.tog_ktg = INPUT.ktg2 )) 
    SELECT stasjon_nv, 
           utg_dt, 
           tog_nr, 
           utgstasjon_kd, 
           endestasjon_kd, 
           innstilt, 
           delinnstilt, 
           tog_ktg, 
           togtype_nv, 
           linjenummer, 
           lengde, 
           CASE 
             WHEN lok_ty IS NOT NULL THEN lok_ty 
             ELSE vogn_nr 
           END motorvogntype, 
           vogn_nr, 
           sporfelt_nr, 
           virksporfelt_nr, 
           plansporfelt_nr, 
           planlagt_ankomst, 
           faktisk_ankomst, 
           planlagt_avgang, 
           faktisk_avgang, 
           stasjonsbruk, 
           asstog_nr 
    FROM   utvalg 
