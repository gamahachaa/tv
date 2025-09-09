select

  ROUND(ROUND(2 * lpos.preis / 0.79, 1) / 2, 2) as Preis_Prix,

  prod.name,

  CONCAT(

    REPLACE(lpos.menge, ".000", ""),

    " ",

    REPLACE(

      REPLACE(

        REPLACE(lpos.einheit, "Bund", "Bnd/bte"),

        "Stueck",

        "St/pc"

      ),

      "Kilogramm",

      "kg"

    )

  ) as menge_portion,

  REPLACE(

    REPLACE(

      l.abotyp_beschrieb,

      "Grosser Korb",

      "Gross/Grand"

    ),

    "Kleiner Korb",

    "Klein/Petit"

  ) as Abo,

  lpos.produkt_beschrieb as produkt_produit

from

  Lieferposition lpos

  left join Lieferung l on lpos.lieferung_id = l.id

  left join Produzent prod on prod.id = lpos.produzent_id

where

  l.lieferplanung_id = 310

  and (

    l.abotyp_beschrieb = "Grosser Korb"

    or l.abotyp_beschrieb = "Kleiner Korb"

    or l.abotyp_beschrieb = "Mini Korb"

  )

order by

  Abo,

  lpos.produzent_kurzzeichen;
