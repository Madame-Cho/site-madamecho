<?php
/*
 * Madame Cho : espace de gestion. Le manuel (demande d'Olivier, 06/11/2026) : comment fonctionne la gestion, écran par écran,
 * en mots simples, en français et en coréen. Une seule source : ce fichier sert la page « Manuel » (menu Plus)
 * et le texte que lit l'assistant pour répondre à Youngmi. Quand un écran change, on met le manuel à jour ici.
 * Aucune donnée personnelle, aucun secret (dépôt public, R-08).
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

/**
 * Les sections du manuel. Chaque texte est une paire [français, coréen] ; un texte qui commence par « - » est une puce.
 * Gras autorisé (<b>…</b>) : c'est le seul HTML, écrit ici à la main.
 */
function manuel_sections(): array {
    return [
        ['id' => 'parcours', 'titre' => ['Le parcours d\'une commande', '주문 진행 과정'], 'textes' => [
            ['Une commande passe par des <b>étapes</b>. À chaque étape, l\'écran de la commande propose le bon bouton.',
             '주문은 <b>단계</b>를 따라 진행돼요. 단계마다 주문 화면에 알맞은 버튼이 나와요.'],
            ['- <b>À répondre</b> : une demande arrive par le site (elle s\'ajoute toute seule) ou vous la saisissez avec « Nouvelle commande » (Instagram, téléphone, marché…). Le site promet une réponse sous 24 heures.',
             '- <b>답장 필요</b>: 사이트로 문의가 오면 자동으로 추가돼요. Instagram, 전화, 장터에서 받은 주문은 ‘새 주문’으로 직접 입력해요. 사이트는 24시간 안에 답장한다고 약속해요.'],
            ['- <b>Proposition</b> : « Préparer la proposition » donne un email prêt. Vous le relisez, vous l\'envoyez depuis contact@madamecho.com, puis vous appuyez sur « J\'ai envoyé la proposition ».',
             '- <b>견적</b>: ‘견적 이메일 준비’를 누르면 이메일이 준비돼요. 읽어 보고 contact@madamecho.com에서 보낸 다음 ‘견적을 보냈어요’를 눌러요.'],
            ['- <b>Confirmée</b> : quand le client accepte par écrit, « Confirmer la commande » avec le jour et l\'heure du retrait. Un lien ajoute le retrait à votre agenda Google.',
             '- <b>확정</b>: 고객이 글로 수락하면 ‘주문 확정’을 누르고 픽업 날짜와 시간을 적어요. 링크 하나로 구글 캘린더에 픽업을 넣을 수 있어요.'],
            ['- <b>Remise</b> : le jour du retrait, « Commande remise au client », avec le paiement. Proposez un avis Google à tous les clients, sans trier.',
             '- <b>전달 완료</b>: 픽업 날, ‘고객에게 전달 완료’를 누르고 결제를 적어요. 모든 고객에게 똑같이 구글 리뷰를 부탁해 보세요.'],
            ['- <b>Sans suite</b> ou <b>Annulée</b> : pas de réponse du client, ou une annulation : la demande sort des listes. Les demandes sans suite sont effacées au bout de 12 mois.',
             '- <b>진행 안 됨</b> 또는 <b>취소</b>: 고객이 답이 없거나 취소하면 문의가 목록에서 빠져요. 진행 안 된 문의는 12개월 뒤에 지워져요.'],
            ['Un <b>problème</b> (retard, réclamation, paiement) se signale dans la commande : il reste visible partout jusqu\'à « Marquer résolu ».',
             '<b>문제</b>(지연, 불만, 결제)는 주문 화면에서 신고해요. ‘해결됨으로 표시’를 누를 때까지 어디서나 보여요.'],
        ]],
        ['id' => 'aujourdhui', 'titre' => ['Aujourd\'hui', '오늘'], 'textes' => [
            ['C\'est l\'écran d\'accueil : ce qu\'il y a à faire aujourd\'hui, en quatre chiffres puis en listes.',
             '첫 화면이에요. 오늘 할 일을 숫자 네 개와 목록으로 보여 줘요.'],
            ['- Les chiffres : <b>À répondre</b> (avec le nombre en retard), <b>Retraits à venir</b> sur 7 jours, les <b>Remises du mois</b> avec le total encaissé, et le <b>Panier moyen</b>.',
             '- 숫자: <b>답장 필요</b>(늦은 건수 포함), 7일 동안의 <b>다가오는 픽업</b>, 이달 <b>전달 완료</b>와 총액, <b>평균 주문 금액</b>.'],
            ['- Les listes : <b>Retraits passés à clôturer</b> (à marquer remises ou annulées), <b>Problèmes ouverts</b>, <b>Demandes à répondre</b>, <b>Retraits des 7 prochains jours</b> (jour par jour), <b>Propositions en attente de réponse du client</b>, <b>Acomptes attendus</b> et, pour les restaurants, <b>Relevés à facturer</b>.',
             '- 목록: <b>마무리할 지난 픽업</b>(전달 완료나 취소로 표시), <b>해결 안 된 문제</b>, <b>답장할 문의</b>, <b>앞으로 7일 동안의 픽업</b>(날짜별), <b>고객 답장을 기다리는 견적</b>, <b>받아야 할 선금</b>, 그리고 레스토랑의 <b>청구할 내역서</b>.'],
            ['- Le bouton <b>Fiche de production</b> ouvre la liste à imprimer pour l\'atelier.',
             '- <b>생산 시트</b> 버튼은 작업실용 인쇄 목록을 열어요.'],
            ['Une liste vide est une bonne nouvelle : il n\'y a rien à faire de ce côté-là.',
             '목록이 비어 있으면 좋은 소식이에요. 그쪽에는 할 일이 없다는 뜻이에요.'],
        ]],
        ['id' => 'commandes', 'titre' => ['Commandes', '주문'], 'textes' => [
            ['La liste de toutes les demandes et commandes, avec des onglets : <b>En cours</b>, <b>À venir</b> (retrait confirmé), <b>Terminées</b>, <b>Problèmes</b> et <b>Toutes</b>.',
             '모든 문의와 주문 목록이에요. 탭: <b>진행 중</b>, <b>예정</b>(픽업 확정), <b>끝난 주문</b>, <b>문제</b>, <b>전체</b>.'],
            ['- La recherche trouve une commande par le nom du client, sa référence (MC-…), son email ou le numéro de facture.',
             '- 검색으로 고객 이름, 주문 번호(MC-…), 이메일, 청구서 번호로 주문을 찾아요.'],
            ['- Chaque carte montre l\'étape, le retrait, le montant et le paiement. On touche la carte pour ouvrir la commande.',
             '- 카드마다 단계, 픽업, 금액, 결제가 보여요. 카드를 누르면 주문이 열려요.'],
            ['- Le bouton <b>+</b> en haut (« Nouvelle commande ») sert pour une commande reçue ailleurs que sur le site.',
             '- 위쪽 <b>+</b> 버튼(‘새 주문’)은 사이트 밖에서 받은 주문을 넣을 때 써요.'],
        ]],
        ['id' => 'commande', 'titre' => ['Une commande', '주문 하나'], 'textes' => [
            ['L\'écran d\'une commande montre le <b>client</b> (avec un lien vers sa fiche), le <b>contenu</b> (gâteaux, tailles, parfums, plaque en chocolat, personnalisation), le <b>montant et le paiement</b>, puis les boutons de l\'étape en cours.',
             '주문 화면에는 <b>고객</b>(고객 정보 링크 포함), <b>내용</b>(케이크, 크기, 맛, 초콜릿 플레이트, 맞춤 내용), <b>금액과 결제</b>, 그리고 지금 단계의 버튼이 있어요.'],
            ['- <b>À répondre</b> : « Préparer la proposition » (email prêt à copier) puis « J\'ai envoyé la proposition ». Ou « Classer sans suite ».',
             '- <b>답장 필요</b>: ‘견적 이메일 준비’(복사할 수 있는 이메일), 그다음 ‘견적을 보냈어요’. 또는 ‘진행 안 됨으로 정리’.'],
            ['- <b>Proposition</b> : « Confirmer la commande » avec la date et l\'heure du retrait, ou « Classer sans suite » si le client ne répond plus.',
             '- <b>견적</b>: ‘주문 확정’을 누르고 픽업 날짜와 시간을 적어요. 고객이 더 답하지 않으면 ‘진행 안 됨으로 정리’.'],
            ['- <b>Confirmée</b> : « Commande remise au client » le jour du retrait, avec le paiement (payé, acompte, moyen). Un lien ajoute le retrait à l\'agenda.',
             '- <b>확정</b>: 픽업 날 ‘고객에게 전달 완료’를 누르고 결제(완료, 선금, 결제 수단)를 적어요. 링크로 픽업을 캘린더에 넣을 수 있어요.'],
            ['- <b>Remise</b> : « J\'ai demandé un avis » quand vous avez demandé un avis Google, puis « L\'avis est arrivé ».',
             '- <b>전달 완료</b>: 구글 리뷰를 부탁했으면 ‘리뷰를 부탁했어요’, 올라오면 ‘리뷰가 올라왔어요’.'],
            ['- <b>Autres actions</b> : « Modifier » (contenu, retrait, prix), « Faire une copie (client régulier) » pour une commande qui revient, « Changer l\'étape à la main », « Modifier le paiement », « Annuler la commande » (par le client ou par Madame Cho, avec le motif), « Rouvrir la demande ».',
             '- <b>다른 작업</b>: ‘수정’(내용, 픽업, 가격), ‘복사해서 새 주문 (단골 고객)’, ‘단계 직접 바꾸기’, ‘결제 수정’, ‘주문 취소’(고객 요청 또는 Madame Cho 결정, 이유와 함께), ‘문의 다시 열기’.'],
            ['- <b>Signaler un problème</b> : un retard, une réclamation, un paiement qui n\'arrive pas. Le problème reste visible sur Aujourd\'hui et dans Commandes › Problèmes jusqu\'à « Marquer résolu ».',
             '- <b>문제 신고</b>: 지연, 불만, 안 들어오는 결제. ‘해결됨으로 표시’를 누를 때까지 오늘 화면과 주문 › 문제 탭에 계속 보여요.'],
            ['- <b>Commentaires et historique</b> : vos notes (utiles et factuelles) et tout ce que la gestion a enregistré, avec la date et la personne.',
             '- <b>메모와 기록</b>: 내 메모(짧고 사실만)와 관리 시스템이 저장한 모든 일, 날짜와 사람과 함께.'],
            ['Les emails préparés sont en français et partent de votre boîte contact@ : la gestion ne les envoie jamais elle-même.',
             '준비된 이메일은 프랑스어이고 contact@ 메일함에서 보내요. 관리 시스템이 스스로 보내는 일은 없어요.'],
        ]],
        ['id' => 'nouvelle', 'titre' => ['Nouvelle commande', '새 주문'], 'textes' => [
            ['Pour une commande reçue par Instagram, téléphone, au marché ou sur place. Le formulaire s\'écrit en <b>français</b> : ce que vous tapez part tel quel dans les emails et les factures.',
             'Instagram, 전화, 장터, 매장에서 받은 주문을 넣는 화면이에요. 입력은 <b>프랑스어</b>로 해요. 적은 내용이 이메일과 청구서에 그대로 들어가요.'],
            ['- <b>Le client</b> : tapez son email ou son téléphone. Si la gestion le connaît déjà, la commande va sur sa fiche (c\'est l\'email ou le téléphone qui compte, jamais le nom). Sinon, remplissez prénom, nom et ville : la fiche se crée.',
             '- <b>고객</b>: 이메일이나 전화번호를 적어요. 이미 아는 고객이면 주문이 그 고객 정보에 들어가요(이름이 아니라 이메일이나 전화번호로 알아봐요). 모르는 고객이면 이름, 성, 도시를 적으면 고객 정보가 만들어져요.'],
            ['- <b>Les gâteaux</b> : une ligne par gâteau, avec la taille ou le parfum, le nombre et, si le client le veut, le texte de la plaque en chocolat. Pour une commande personnalisée, décrivez-la dans le cadre prévu.',
             '- <b>케이크</b>: 케이크마다 한 줄씩, 크기나 맛, 개수, 원하면 초콜릿 플레이트 문구를 적어요. 맞춤 주문은 정해진 칸에 설명을 적어요.'],
            ['- <b>Le retrait</b> : la date et le moment (matin, midi, après-midi, soir) ; l\'heure précise se note à la confirmation.',
             '- <b>픽업</b>: 날짜와 시간대(오전, 점심, 오후, 저녁). 정확한 시간은 확정할 때 적어요.'],
            ['- <b>Reçue par</b> : d\'où vient la commande (Instagram, téléphone, marché…). Une demande du site arrivée après un scan du QR code d\'un emballage est marquée « QR code » toute seule.',
             '- <b>접수 경로</b>: 주문이 들어온 경로예요(Instagram, 전화, 장터…). 포장의 QR 코드를 스캔한 뒤 들어온 사이트 문의는 자동으로 ‘QR 코드’로 표시돼요.'],
            ['- <b>Enregistrer</b> crée la demande à l\'étape « À répondre » (ou plus loin si vous changez l\'étape ensuite).',
             '- <b>저장</b>을 누르면 ‘답장 필요’ 단계로 문의가 만들어져요(나중에 단계를 바꿀 수 있어요).'],
        ]],
        ['id' => 'clients', 'titre' => ['Clients', '고객'], 'textes' => [
            ['La liste des clients, particuliers et professionnels, avec la recherche par nom, email, téléphone ou ville.',
             '개인과 업체 고객 목록이에요. 이름, 이메일, 전화번호, 도시로 검색해요.'],
            ['- La <b>fiche</b> d\'un client : ses <b>coordonnées</b>, ses <b>commandes et demandes</b>, l\'<b>avis Google</b> (demandé, reçu), les <b>commentaires et l\'historique</b>.',
             '- 고객 <b>정보</b>: <b>연락처</b>, <b>주문과 문의</b>, <b>구글 리뷰</b>(요청함, 받음), <b>메모와 기록</b>.'],
            ['- <b>Fiche et droits du client</b> : « Modifier » la fiche ; si le client demande ses données, « Télécharger ses données » donne le fichier à lui envoyer depuis contact@ dans le mois ; s\'il demande l\'effacement, « Effacer ses données » garde seulement le numéro, les dates et les montants.',
             '- <b>고객 정보와 권리</b>: ‘수정’으로 정보를 고쳐요. 고객이 자기 정보를 요청하면 ‘고객 정보 다운로드’로 받은 파일을 한 달 안에 contact@에서 보내요. 삭제를 요청하면 ‘고객 정보 삭제’로 번호, 날짜, 금액만 남겨요.'],
            ['- <b>Un client qui revient</b> est reconnu par son <b>email</b> ou son <b>téléphone</b>, jamais par son nom (deux personnes peuvent porter le même nom, et un nom peut être mal écrit).',
             '- <b>다시 온 고객</b>은 <b>이메일</b>이나 <b>전화번호</b>로 알아봐요. 이름으로는 알아보지 않아요(같은 이름이 둘일 수도 있고, 이름을 잘못 적을 수도 있으니까요).'],
            ['- <b>Doublon possible</b> : quand une nouvelle fiche ressemble à une autre (même téléphone avec un autre email, même prénom et nom), la gestion le signale sur la fiche. « Fusionner les deux fiches » garde la plus ancienne avec toutes les commandes ; « Ce n\'est pas la même personne » enlève l\'alerte.',
             '- <b>중복 가능성</b>: 새 고객 정보가 다른 고객과 비슷하면(전화번호는 같은데 이메일이 다름, 이름과 성이 같음) 고객 정보에 표시가 떠요. ‘두 고객 정보 합치기’는 더 오래된 정보를 남기고 주문을 모두 모아요. ‘같은 사람이 아니에요’는 표시를 없애요.'],
        ]],
        ['id' => 'restaurants', 'titre' => ['Restaurants (clients professionnels)', '레스토랑 (업체 고객)'], 'textes' => [
            ['Un restaurant commande souvent, par petites livraisons. Dans la gestion, c\'est <b>une commande par livraison</b>, et une facture par mois.',
             '레스토랑은 자주, 조금씩 배달 주문을 해요. 관리 시스템에서는 <b>배달 한 번이 주문 하나</b>이고, 청구서는 한 달에 한 번이에요.'],
            ['- Sur la fiche du restaurant, <b>Nouvelle livraison</b> ouvre une commande avec la <b>gamme pro</b> (prix hors taxes) et la date de livraison. Le jour venu, le bouton <b>Livrée</b>.',
             '- 레스토랑 고객 정보에서 <b>새 배달</b>을 누르면 <b>업체용 제품</b>(부가세 별도 가격)과 배달 날짜가 있는 주문이 열려요. 배달한 날에는 <b>배달 완료</b> 버튼.'],
            ['- <b>Relevés du mois</b> : chaque mois, le relevé additionne les livraisons livrées qui n\'ont pas encore de facture (HT, TVA, TTC). Vous faites la facture dans Qonto à partir du relevé, puis vous notez son numéro et le paiement dans <b>Facture et paiement</b> : c\'est noté d\'un coup sur ces livraisons, jamais par-dessus une facture déjà notée.',
             '- <b>월별 내역서</b>: 매달 내역서가 아직 청구서가 없는 배달 완료 건을 모두 더해요(부가세 별도, 부가세, 부가세 포함). 내역서를 보고 Qonto에서 청구서를 만든 다음 <b>청구서와 결제</b>에 청구서 번호와 결제를 적어요. 그 배달들에 한 번에 적히고, 이미 적힌 청구서 번호는 덮어쓰지 않아요.'],
            ['- Sur Aujourd\'hui, <b>Relevés à facturer</b> rappelle les mois passés qui n\'ont pas encore de numéro de facture.',
             '- 오늘 화면의 <b>청구할 내역서</b>는 아직 청구서 번호가 없는 지난달들을 알려 줘요.'],
            ['- La gamme pro et ses prix se gèrent dans Produits › <b>Gamme pro (restaurants)</b>.',
             '- 업체용 제품과 가격은 제품 › <b>업체용 제품 (레스토랑)</b>에서 관리해요.'],
        ]],
        ['id' => 'produits', 'titre' => ['Produits', '제품'], 'textes' => [
            ['La carte du site, telle que les clients la voient, avec pour chaque gâteau sa fiche, ses recettes, ses coûts et ses ventes. Un produit est <b>à la carte</b>, <b>sur demande seulement</b>, dans la <b>gamme pro</b> ou <b>retiré</b>.',
             '고객이 보는 사이트 메뉴예요. 케이크마다 제품 시트, 레시피, 원가, 판매가 있어요. 제품 상태는 <b>메뉴에 있음</b>, <b>요청 시에만</b>, <b>업체용 제품</b>, <b>판매 중단</b> 중 하나예요.'],
            ['- <b>Sur le site</b> : le texte et les prix publiés (ils se changent avec Olivier, pas ici).',
             '- <b>사이트에 보이는 내용</b>: 공개된 설명과 가격이에요(여기서가 아니라 Olivier와 함께 바꿔요).'],
            ['- <b>Fiche technique</b> : le lien vers la fiche du Drive et la date de validation.',
             '- <b>제품 시트</b>: Drive 시트 링크와 확인 날짜.'],
            ['- <b>Allergènes</b> : ceux de la recette, comparés à ceux affichés sur le site. Un allergène présent dans la recette mais absent du site est signalé : à corriger sur le site.',
             '- <b>알레르기 유발 성분</b>: 레시피의 성분과 사이트에 적힌 성분을 비교해요. 레시피에는 있는데 사이트에 없는 성분은 표시돼요. 사이트를 고쳐야 해요.'],
            ['- <b>Recettes, coûts et marges</b> : une recette par taille ou parfum, avec les ingrédients, le coût matières et la marge sur matières (le travail, l\'énergie et les pertes ne sont pas comptés). « Copier une recette vers une autre taille ou un autre parfum » évite de tout ressaisir.',
             '- <b>레시피, 원가, 마진</b>: 크기나 맛마다 레시피 하나, 재료, 재료비, 재료 마진(인건비, 에너지, 손실은 포함되지 않아요). ‘레시피를 다른 크기나 맛으로 복사’하면 다시 입력하지 않아도 돼요.'],
            ['- <b>Ventes des 12 derniers mois</b> et <b>Historique</b> des modifications.',
             '- <b>최근 12개월 판매</b>와 수정 <b>기록</b>.'],
            ['- <b>Gamme pro (restaurants)</b> : les produits vendus aux restaurants, avec le prix hors taxes, l\'unité et la TVA. « Ajouter un produit à la gamme pro » pour un nouveau produit.',
             '- <b>업체용 제품 (레스토랑)</b>: 레스토랑에 파는 제품, 부가세 별도 가격, 판매 단위, 부가세. 새 제품은 ‘업체용 제품 추가’.'],
        ]],
        ['id' => 'ingredients', 'titre' => ['Ingrédients', '재료'], 'textes' => [
            ['La liste des ingrédients avec leur prix d\'achat, leur unité, leurs allergènes et leur fournisseur. Les recettes s\'en servent pour calculer les coûts et les allergènes.',
             '재료 목록이에요. 구입 가격, 단위, 알레르기 유발 성분, 공급처가 있어요. 레시피가 이걸로 원가와 알레르기 유발 성분을 계산해요.'],
            ['- Un ingrédient sans prix rend le coût de la recette trop bas : la gestion le signale.',
             '- 가격이 없는 재료가 있으면 레시피 원가가 실제보다 낮게 나와요. 관리 시스템이 알려 줘요.'],
            ['- Un ingrédient se modifie depuis sa fiche ; « Utilisé dans » montre les produits concernés.',
             '- 재료는 재료 정보 화면에서 고쳐요. ‘사용하는 제품’에 관련 제품이 보여요.'],
        ]],
        ['id' => 'production', 'titre' => ['Fiche de production', '생산 시트'], 'textes' => [
            ['La liste de ce qu\'il faut préparer, jour par jour, pour les commandes confirmées : gâteaux, tailles, parfums, plaques, personnalisation. Sans email ni téléphone : elle peut rester dans l\'atelier.',
             '확정된 주문을 날짜별로 준비할 목록이에요. 케이크, 크기, 맛, 플레이트, 맞춤 내용. 이메일과 전화번호는 없어서 작업실에 두어도 괜찮아요.'],
            ['- Le bouton « Imprimer » lance l\'impression depuis le téléphone ou l\'ordinateur.',
             '- ‘인쇄’ 버튼으로 휴대폰이나 컴퓨터에서 바로 인쇄해요.'],
        ]],
        ['id' => 'statistiques', 'titre' => ['Statistiques', '통계'], 'textes' => [
            ['- <b>Ventes</b> : le chiffre d\'affaires, les commandes remises, le panier moyen, les clients et les fidèles, les demandes abouties, la réponse aux demandes du site en moins de 24 h, les avis Google, les gâteaux vendus, les canaux, et où en sont les demandes.',
             '- <b>판매</b>: 매출, 전달 완료 주문, 평균 주문 금액, 고객과 단골, 성사된 문의, 24시간 안에 답장한 사이트 문의, 구글 리뷰, 판매한 케이크, 경로, 문의 진행 상황.'],
            ['- <b>Site internet</b> : les visites du site madamecho.com sur 30 jours (Matomo), les pages les plus vues, téléphone ou ordinateur, d\'où viennent les visiteurs, les scans du QR code imprimé sur les emballages et les demandes arrivées par ce QR code.',
             '- <b>웹사이트</b>: 30일 동안의 madamecho.com 방문(Matomo), 가장 많이 본 페이지, 휴대폰과 컴퓨터, 방문자가 온 경로, 포장에 인쇄된 QR 코드 스캔 수와 그 QR 코드로 들어온 문의 수.'],
            ['Les montants sont ceux des commandes remises, TTC.',
             '금액은 전달 완료된 주문의 부가세 포함 금액이에요.'],
        ]],
        ['id' => 'reglages', 'titre' => ['Réglages', '설정'], 'textes' => [
            ['- <b>Langue de l\'écran</b> : coréen ou français, gardé sur cet appareil (le bouton en haut de l\'écran fait la même chose).',
             '- <b>화면 언어</b>: 한국어나 프랑스어, 이 기기에 저장돼요(화면 위쪽 버튼도 같은 일을 해요).'],
            ['- <b>Mon mot de passe</b> : le changer. Au moins 12 caractères ; une petite phrase est facile à retenir. Gardez-le dans Bitwarden.',
             '- <b>내 비밀번호</b>: 바꾸기. 최소 12자. 짧은 문장이 기억하기 쉬워요. Bitwarden에 저장해 두세요.'],
            ['- <b>Emails préparés et avis Google</b> : la signature mise au bas des emails préparés, et le lien pour laisser un avis Google.',
             '- <b>준비된 이메일과 구글 리뷰</b>: 준비된 이메일 아래에 들어가는 서명, 구글 리뷰 링크.'],
            ['- <b>Qui a accès</b> : les personnes qui ont un compte ; inviter quelqu\'un qui travaille pour Madame Cho. Une IA n\'a jamais de compte ici.',
             '- <b>접근 권한이 있는 사람</b>: 계정이 있는 사람들. Madame Cho에서 일하는 사람을 초대할 수 있어요. AI는 여기에 계정을 가질 수 없어요.'],
            // L'état réel, pas une promesse (audit du 07/11, C8) : le réglage est coupé tant que le pilote avec Youngmi n'est pas fait
            parametre('demandes_site') === '1'
                ? ['- <b>Demandes du site</b> : activé. Chaque demande du formulaire du site s\'ajoute ici toute seule.',
                   '- <b>사이트 문의</b>: 켜져 있어요. 사이트 양식으로 온 문의가 자동으로 여기에 추가돼요.']
                : ['- <b>Demandes du site</b> : pas encore activé (Olivier l\'active dans Réglages quand le pilote est fait). En attendant, les demandes du site arrivent seulement par email à contact@madamecho.com : saisissez-les avec « Nouvelle commande ».',
                   '- <b>사이트 문의</b>: 아직 꺼져 있어요(시범 운영이 끝나면 Olivier가 설정에서 켜요). 그동안 사이트 문의는 contact@madamecho.com 이메일로만 와요. ‘새 주문’으로 직접 입력해 주세요.'],
            ['- <b>Statistiques du site (Matomo)</b> et <b>Assistant</b> : les clés d\'accès, collées une fois par Olivier ; elles ne sont jamais réaffichées.',
             '- <b>사이트 통계 (Matomo)</b>와 <b>어시스턴트</b>: Olivier가 한 번 붙여 넣는 접근 키예요. 다시 보이지 않아요.'],
            ['- <b>Conservation des données (RGPD)</b> : chaque nuit, la gestion efface ce qui a dépassé sa durée (demandes sans suite après 12 mois, clients sans commande depuis 3 ans, journal des connexions après 6 mois).',
             '- <b>데이터 보관 (GDPR)</b>: 매일 밤 보관 기간이 지난 것을 지워요(진행 안 된 문의는 12개월 뒤, 3년 동안 주문이 없는 고객, 6개월 지난 접속 기록).'],
            ['- <b>Miroir pour le Drive</b>, <b>Reprise des données et export</b>, <b>Exporter</b> : la copie pour le Google Sheet du Drive, l\'export complet des données (pour une analyse ou une reprise ; la vraie sauvegarde est celle de l\'hébergement, qu\'Olivier vérifie) et les exports pour un tableur. À voir avec Olivier.',
             '- <b>Drive용 사본</b>, <b>데이터 가져오기와 내보내기</b>, <b>내보내기</b>: Drive의 Google Sheet용 사본, 전체 데이터 내보내기(분석이나 가져오기용이에요. 진짜 백업은 호스팅 쪽 백업이고 Olivier가 확인해요), 스프레드시트용 내보내기. Olivier와 함께 봐요.'],
            ['- <b>Contrôle technique</b> et <b>Dernières connexions</b> : l\'état de la gestion et qui s\'est connecté.',
             '- <b>기술 점검</b>과 <b>최근 접속</b>: 관리 시스템 상태와 접속한 사람.'],
        ]],
        ['id' => 'assistant', 'titre' => ['L\'assistant', '어시스턴트'], 'textes' => [
            ['L\'assistant (menu Plus) répond à vos questions sur la gestion, vous aide quand quelque chose ne marche pas et discute vos idées d\'amélioration. Vous pouvez lui écrire en coréen ou en français.',
             '어시스턴트(더보기 메뉴)는 관리 시스템에 대한 질문에 답하고, 뭔가 안 될 때 도와주고, 개선 아이디어를 같이 이야기해요. 한국어나 프랑스어로 써도 돼요.'],
            ['- Il connaît ce manuel, et rien d\'autre : il ne voit pas vos commandes ni vos clients, et il ne peut rien faire à votre place.',
             '- 어시스턴트는 이 설명서만 알아요. 주문이나 고객 정보는 보지 못하고, 대신 무언가를 해 줄 수도 없어요.'],
            ['- Il vous dit franchement quand il pense que vous vous trompez, et pourquoi.',
             '- 내가 틀렸다고 생각하면 솔직하게, 이유와 함께 말해 줘요.'],
            ['- Quand un vrai problème ou un vrai besoin apparaît, il prépare un <b>rapport</b>. Un bouton l\'envoie à contact@madamecho.com ; Olivier le lit et regarde comment améliorer la gestion. Rien ne part sans votre bouton.',
             '- 진짜 문제나 진짜 필요한 개선이 보이면 <b>보고서</b>를 준비해요. 버튼을 누르면 contact@madamecho.com으로 보내져요. Olivier가 읽고 관리 시스템을 어떻게 고칠지 봐요. 버튼을 누르기 전에는 아무것도 나가지 않아요.'],
            ['- Parlez des clients et des commandes par leur numéro (CL-…, MC-…), sans coordonnées, et jamais d\'information de santé : les échanges passent par l\'entreprise qui fait fonctionner l\'assistant (Anthropic).',
             '- 고객과 주문은 번호(CL-…, MC-…)로 말해 주세요. 연락처는 적지 말고, 건강 정보는 절대 적지 마세요. 대화는 어시스턴트를 운영하는 회사(Anthropic)를 거쳐요.'],
            ['- <b>Écrire à Olivier</b>, sous la conversation : votre message part tel quel à contact@madamecho.com, sans l\'assistant, en français ou en coréen.',
             '- 대화 아래 <b>Olivier에게 쓰기</b>: 어시스턴트를 거치지 않고 메시지가 그대로 contact@madamecho.com으로 가요. 한국어로 써도 돼요.'],
            ['- Il peut se tromper : en cas de doute, ce manuel fait foi, et Olivier répond aux questions techniques.',
             '- 어시스턴트도 틀릴 수 있어요. 헷갈리면 이 설명서가 기준이고, 기술적인 질문은 Olivier가 답해요.'],
        ]],
        ['id' => 'regles', 'titre' => ['Les règles à garder en tête', '꼭 기억할 규칙'], 'textes' => [
            ['- <b>Aucun email ne part tout seul</b> : la gestion prépare les textes, c\'est vous qui les envoyez depuis contact@madamecho.com.',
             '- <b>이메일은 저절로 나가지 않아요</b>: 관리 시스템이 글을 준비하고, 보내는 건 contact@madamecho.com에서 직접 해요.'],
            ['- Ce que vous écrivez reste en <b>français</b> (noms, gâteaux, commentaires) : il part tel quel dans les emails, les factures et l\'historique. L\'écran, lui, peut être en coréen.',
             '- 직접 적는 내용(이름, 케이크, 메모)은 <b>프랑스어</b>로 써요. 이메일, 청구서, 기록에 그대로 쓰여요. 화면은 한국어로 봐도 돼요.'],
            ['- <b>Jamais d\'information de santé</b> : pour une allergie, seulement l\'ingrédient à éviter.',
             '- <b>건강 정보는 적지 않아요</b>: 알레르기는 피해야 할 재료만 적어요.'],
            ['- Les <b>mots de passe</b> et les <b>clés</b> ne se partagent avec personne, pas même avec l\'assistant. Déconnectez-vous sur un appareil qui n\'est pas le vôtre.',
             '- <b>비밀번호</b>와 <b>키</b>는 누구와도, 어시스턴트와도 공유하지 않아요. 내 기기가 아니면 로그아웃하세요.'],
            ['- Un point des commandes avec Olivier ou son agent Mathieu est prévu le mardi et le vendredi ; tant qu\'il n\'est pas en place, vérifiez vous-même Aujourd\'hui chaque jour.',
             '- 화요일과 금요일에 Olivier나 에이전트 Mathieu와 주문 점검이 예정되어 있어요. 아직 시작 전이니 그동안은 매일 ‘오늘’ 화면을 직접 확인해 주세요.'],
            ['- Quelque chose qui ne marche plus, une question sur les données ou les factures : Olivier.',
             '- 뭔가 더 이상 안 될 때, 데이터나 청구서에 대한 질문: Olivier.'],
        ]],
    ];
}

/** Le titre d'une section dans la langue de l'écran. */
function manuel_titre(array $s): string { return fr_ko($s['titre'][0], $s['titre'][1]); }

/** Une section en HTML : paragraphes et puces, dans la langue de l'écran. */
function manuel_html(array $s): string {
    $h = '';
    $liste = '';
    $fermer = function () use (&$h, &$liste): void { if ($liste !== '') { $h .= '<ul class="puces">' . $liste . '</ul>'; $liste = ''; } };
    foreach ($s['textes'] as [$fr, $ko]) {
        $t = fr_ko($fr, $ko);
        if (str_starts_with($fr, '- ')) $liste .= '<li>' . substr($t, 2) . '</li>';
        else { $fermer(); $h .= '<p>' . $t . '</p>'; }
    }
    $fermer();
    return $h;
}

/** Le manuel en texte simple, pour l'assistant : titres et puces, dans une langue. */
function manuel_texte(string $langue): string {
    $i = $langue === 'ko' ? 1 : 0;
    $t = '';
    foreach (manuel_sections() as $s) {
        $t .= "\n## " . $s['titre'][$i] . "\n";
        foreach ($s['textes'] as $paire) $t .= html_entity_decode(strip_tags($paire[$i]), ENT_QUOTES | ENT_HTML5, 'UTF-8') . "\n";
    }
    return trim($t);
}

/** ?p=manuel : le manuel, écran par écran, avec un sommaire. */
function page_manuel(): void {
    $sections = manuel_sections();
    $som = '';
    $corps = '';
    foreach ($sections as $s) {
        $som .= '<a href="#' . h($s['id']) . '">' . manuel_titre($s) . '</a>';
        $corps .= bloc(manuel_titre($s), manuel_html($s) . '<p class="aide"><a href="#sommaire">' . fr_ko('↑ Sommaire', '↑ 차례') . '</a></p>', 'manuel', $s['id']);
    }
    $c = titre_page('Manuel', fr_ko('Comment fonctionne la gestion, écran par écran', '관리 시스템 사용법, 화면별로'), '', u('plus'))
       . '<p class="aide">' . fr_ko('En cas de doute, ce manuel fait foi. Une question qui n\'y trouve pas sa réponse ? L\'<a href="' . h(u('assistant')) . '">assistant</a> est là pour ça.',
                                   '헷갈릴 때는 이 설명서가 기준이에요. 여기에 답이 없는 질문은 <a href="' . h(u('assistant')) . '">어시스턴트</a>에게 물어보세요.') . '</p>'
       . '<nav class="sommaire" id="sommaire" aria-label="Sommaire">' . $som . '</nav>' . $corps;
    mise_en_page('Manuel', $c, 'manuel');
}
