<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 4 semaines de versets thématiques (28 jours).
 * Rotation : Fidélité → Témoignages → Promesses → Obéissance
 * Les versets d'une même semaine sont liés par un fil conducteur.
 */
class DailyVerseSeeder extends Seeder
{
    public function run(): void
    {
        $pool = self::versePool();
        $startDate = now()->toDateString();

        foreach ($pool as $i => $entry) {
            $date = date('Y-m-d', strtotime("{$startDate} +{$i} days"));
            DB::table('daily_verses')->insertOrIgnore([
                'verse_text'     => $entry['text'],
                'reference'      => $entry['ref'],
                'scheduled_date' => $date,
                'theme'          => $entry['theme'],
                'week_number'    => $entry['week'],
                'is_active'      => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    public static function versePool(): array
    {
        return [
            // ── SEMAINE 1 : Fidélité de Dieu ──────────────────────────────
            ['week'=>1,'theme'=>'faithfulness','ref'=>'Lamentations 3:22-23',
             'text'=>"Les grâces de l'Éternel ne sont pas épuisées, ses compassions ne cessent point. Elles se renouvellent chaque matin. Ta fidélité est grande !"],
            ['week'=>1,'theme'=>'faithfulness','ref'=>'Deutéronome 7:9',
             'text'=>"Sache donc que c'est l'Éternel, ton Dieu, qui est Dieu. Ce Dieu fidèle garde son alliance et sa grâce envers ceux qui l'aiment et qui observent ses commandements, jusqu'à mille générations."],
            ['week'=>1,'theme'=>'faithfulness','ref'=>'Psaume 36:5',
             'text'=>"Éternel ! Ta bonté s'étend jusqu'aux cieux, ta fidélité jusqu'aux nues."],
            ['week'=>1,'theme'=>'faithfulness','ref'=>'Psaume 89:1-2',
             'text'=>"Je chanterai éternellement les grâces de l'Éternel ; j'annoncerai ta fidélité de bouche en bouche, à toutes les générations. Car je dis : Ta grâce subsistera toujours, ta fidélité est aussi ferme que les cieux."],
            ['week'=>1,'theme'=>'faithfulness','ref'=>'1 Corinthiens 1:9',
             'text'=>"Dieu est fidèle, lui qui vous a appelés à la communion de son Fils, Jésus-Christ notre Seigneur."],
            ['week'=>1,'theme'=>'faithfulness','ref'=>'2 Thessaloniciens 3:3',
             'text'=>"Mais le Seigneur est fidèle ; il vous affermira et vous gardera du Malin."],
            ['week'=>1,'theme'=>'faithfulness','ref'=>'Hébreux 10:23',
             'text'=>"Retenons sans fléchir la confession de notre espérance, car celui qui a promis est fidèle."],

            // ── SEMAINE 2 : Importance des témoignages ────────────────────
            ['week'=>2,'theme'=>'testimonies','ref'=>'Psaume 107:1-2',
             'text'=>"Louez l'Éternel, car il est bon, car sa grâce dure à toujours ! Que le disent les rachetés de l'Éternel, ceux qu'il a délivrés de la main de l'adversaire."],
            ['week'=>2,'theme'=>'testimonies','ref'=>'Apocalypse 12:11',
             'text'=>"Ils l'ont vaincu à cause du sang de l'Agneau et à cause de la parole de leur témoignage, et ils n'ont pas aimé leur vie jusqu'à craindre la mort."],
            ['week'=>2,'theme'=>'testimonies','ref'=>'Marc 5:19',
             'text'=>"Retourne dans ta maison, auprès des tiens, et raconte-leur tout ce que le Seigneur t'a fait, et comment il t'a fait miséricorde."],
            ['week'=>2,'theme'=>'testimonies','ref'=>'Actes 1:8',
             'text'=>"Vous recevrez une puissance, le Saint-Esprit survenant sur vous, et vous serez mes témoins à Jérusalem, dans toute la Judée, dans la Samarie, et jusqu'aux extrémités de la terre."],
            ['week'=>2,'theme'=>'testimonies','ref'=>'Jean 4:39',
             'text'=>"Beaucoup de Samaritains de cette ville crurent en lui, à cause de la parole de la femme qui témoignait : Il m'a dit tout ce que j'ai fait."],
            ['week'=>2,'theme'=>'testimonies','ref'=>'1 Jean 1:3',
             'text'=>"Ce que nous avons vu et entendu, nous vous l'annonçons, à vous aussi, afin que vous aussi vous soyez en communion avec nous. Or notre communion est avec le Père et avec son Fils Jésus-Christ."],
            ['week'=>2,'theme'=>'testimonies','ref'=>'Psaume 66:16',
             'text'=>"Venez, vous tous qui craignez Dieu, et j'annoncerai ce qu'il a fait pour mon âme."],

            // ── SEMAINE 3 : Promesses de Dieu ────────────────────────────
            ['week'=>3,'theme'=>'promises','ref'=>'Jérémie 29:11',
             'text'=>"Car je connais les projets que j'ai formés sur vous, dit l'Éternel, projets de paix et non de malheur, afin de vous donner un avenir et de l'espérance."],
            ['week'=>3,'theme'=>'promises','ref'=>'Matthieu 7:7-8',
             'text'=>"Demandez, et l'on vous donnera ; cherchez, et vous trouverez ; frappez, et l'on vous ouvrira. Car quiconque demande reçoit, celui qui cherche trouve, et l'on ouvre à celui qui frappe."],
            ['week'=>3,'theme'=>'promises','ref'=>'Psaume 37:4',
             'text'=>"Fais de l'Éternel tes délices, et il te donnera ce que ton cœur désire."],
            ['week'=>3,'theme'=>'promises','ref'=>'Ésaïe 41:10',
             'text'=>"Ne crains rien, car je suis avec toi ; ne promène pas des regards inquiets, car je suis ton Dieu ; je te fortifie, je viens à ton secours, je te soutiens de ma droite triomphante."],
            ['week'=>3,'theme'=>'promises','ref'=>'Jean 14:2-3',
             'text'=>"Dans la maison de mon Père il y a plusieurs demeures. Je vais vous préparer une place. Et lorsque je m'en serai allé et que je vous aurai préparé une place, je reviendrai, et je vous prendrai avec moi."],
            ['week'=>3,'theme'=>'promises','ref'=>'Romains 8:28',
             'text'=>"Nous savons, du reste, que toutes choses concourent au bien de ceux qui aiment Dieu, de ceux qui sont appelés selon son dessein."],
            ['week'=>3,'theme'=>'promises','ref'=>'2 Corinthiens 1:20',
             'text'=>"Car toutes les promesses de Dieu sont oui en lui. C'est pourquoi c'est aussi par lui que nous disons Amen à la gloire de Dieu."],

            // ── SEMAINE 4 : Vie d'obéissance ─────────────────────────────
            ['week'=>4,'theme'=>'obedience','ref'=>'Jean 14:15',
             'text'=>"Si vous m'aimez, gardez mes commandements."],
            ['week'=>4,'theme'=>'obedience','ref'=>'Jacques 1:22',
             'text'=>"Mettez en pratique la parole, et ne vous bornez pas à l'écouter, en vous trompant vous-mêmes par de faux raisonnements."],
            ['week'=>4,'theme'=>'obedience','ref'=>'Deutéronome 28:1',
             'text'=>"Si tu obéis fidèlement à la voix de l'Éternel, ton Dieu, si tu gardes et si tu mets en pratique tous ses commandements que je te prescris aujourd'hui, l'Éternel, ton Dieu, te mettra au-dessus de toutes les nations de la terre."],
            ['week'=>4,'theme'=>'obedience','ref'=>'Josué 1:8',
             'text'=>"Que ce livre de la loi ne s'éloigne point de ta bouche ; médite-le jour et nuit, pour agir fidèlement selon tout ce qui y est écrit ; car c'est alors que tu auras du succès dans tes entreprises, c'est alors que tu réussiras."],
            ['week'=>4,'theme'=>'obedience','ref'=>'Matthieu 6:33',
             'text'=>"Cherchez premièrement le royaume et la justice de Dieu ; et toutes ces choses vous seront données par-dessus."],
            ['week'=>4,'theme'=>'obedience','ref'=>'1 Samuel 15:22',
             'text'=>"L'obéissance vaut mieux que les sacrifices, et l'observation de sa parole vaut mieux que la graisse des béliers."],
            ['week'=>4,'theme'=>'obedience','ref'=>'Romains 12:1',
             'text'=>"Je vous exhorte donc, frères, par les compassions de Dieu, à offrir vos corps comme un sacrifice vivant, saint, agréable à Dieu, ce qui sera de votre part un culte raisonnable."],
        ];
    }
}
