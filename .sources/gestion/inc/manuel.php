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
            ['- <b>Proposition</b> : « Envoyer la proposition au client » : indiquez le prix et l\'acompte, changez la phrase d\'accueil si vous voulez, ajoutez un petit mot, vérifiez l\'aperçu, puis « Envoyer ». Le mail part de contact@madamecho.com et la commande passe à « Proposition ». Vous l\'avez envoyée vous-même depuis Gmail ? « Autre façon » puis « J\'ai envoyé la proposition ».',
             '- <b>견적</b>: ‘고객에게 견적 이메일 보내기’: 가격과 선금을 적고, 원하면 인사말을 고치고 짧은 메모를 더한 뒤, 미리보기를 확인하고 ‘보내기’를 눌러요. 이메일은 contact@madamecho.com에서 나가고 주문은 ‘견적’ 단계가 돼요. Gmail에서 직접 보냈다면 ‘다른 방법’에서 ‘견적을 보냈어요’를 눌러요.'],
            ['- <b>Confirmée</b> : quand le client accepte par écrit, « Confirmer la commande » avec le jour et l\'heure du retrait. Un lien ajoute le retrait à votre agenda Google. Ensuite, « Envoyer la confirmation au client » : vous pouvez changer la phrase d\'accueil et ajouter un petit mot, l\'aperçu montre le mail exact, puis « Envoyer » le fait partir de contact@madamecho.com (une copie y arrive). Avec une heure précise, le mail propose au client d\'ajouter le retrait à son agenda (Google, iPhone, Outlook).',
             '- <b>확정</b>: 고객이 글로 수락하면 ‘주문 확정’을 누르고 픽업 날짜와 시간을 적어요. 링크 하나로 구글 캘린더에 픽업을 넣을 수 있어요. 그다음 ‘고객에게 확정 이메일 보내기’: 인사말을 고치고 짧은 메모를 더할 수 있어요. 미리보기에 실제 이메일이 보이고, ‘보내기’를 누르면 contact@madamecho.com에서 나가요(사본이 거기로 와요). 정확한 시간을 적으면 고객이 자기 캘린더(구글, iPhone, Outlook)에 픽업을 넣는 링크도 들어가요.'],
            ['- <b>Rappel</b> : la veille du retrait, la commande apparaît sur Aujourd\'hui dans « Rappels à envoyer ». Ouvrez-la, vérifiez l\'aperçu, puis « Envoyer » : le client reçoit un petit rappel (jour, heure, adresse, reste à régler). Rien ne part tout seul. Pas d\'email, ou le client préfère le SMS ? Copiez la version courte dans un SMS ou WhatsApp, puis « J\'ai envoyé le rappel » (aussi pour un rappel parti de Gmail). Si la confirmation est partie la veille, pas besoin de rappel. Si le jour ou l\'heure change après un rappel, la commande revient avec « Retrait modifié » : prévenez le client. Un rappel oublié la veille reste proposé le jour même.',
             '- <b>픽업 알림</b>: 픽업 전날, 주문이 오늘 화면의 ‘보낼 픽업 알림’에 나와요. 주문을 열고 미리보기를 확인한 뒤 ‘보내기’를 누르면 고객이 짧은 알림(날짜, 시간, 주소, 남은 금액)을 받아요. 저절로 나가는 건 없어요. 이메일이 없거나 고객이 문자를 좋아하면 짧은 버전을 SMS나 WhatsApp에 붙여 넣고 ‘알림을 보냈어요’를 누르세요. 전날 확정 이메일을 보냈으면 알림은 필요 없어요. Gmail로 보낸 알림도 ‘알림을 보냈어요’로 기록해요. 알림 뒤에 날짜나 시간이 바뀌면 주문이 ‘알림 뒤에 픽업이 바뀌었어요’ 표시와 함께 다시 나와요. 고객에게 알려 주세요. 전날 잊은 알림은 당일에도 나와요.'],
            ['- <b>Remise</b> : le jour du retrait, « Commande remise au client », avec le paiement. Ensuite « Demander un avis Google » prépare un mail de remerciement avec le lien d\'avis : « Envoyer », et la demande est notée sur la fiche du client. Proposez-le à tous les clients, sans trier.',
             '- <b>전달 완료</b>: 픽업 날, ‘고객에게 전달 완료’를 누르고 결제를 적어요. 그다음 ‘구글 리뷰 부탁하기’를 누르면 리뷰 링크가 든 감사 이메일이 준비돼요. ‘보내기’를 누르면 고객 정보에 리뷰 요청이 적혀요. 모든 고객에게 똑같이 부탁하세요.'],
            ['- <b>Sans suite</b> ou <b>Annulée</b> : pas de réponse du client, ou une annulation : la demande sort des listes. Les demandes sans suite sont effacées au bout de 12 mois.',
             '- <b>진행 안 됨</b> 또는 <b>취소</b>: 고객이 답이 없거나 취소하면 문의가 목록에서 빠져요. 진행 안 된 문의는 12개월 뒤에 지워져요.'],
            ['Un <b>problème</b> (retard, réclamation, paiement) se signale dans la commande : il reste visible partout jusqu\'à « Marquer résolu ».',
             '<b>문제</b>(지연, 불만, 결제)는 주문 화면에서 신고해요. ‘해결됨으로 표시’를 누를 때까지 어디서나 보여요.'],
        ]],
        ['id' => 'aujourdhui', 'titre' => ['Aujourd\'hui', '오늘'], 'textes' => [
            ['C\'est l\'écran d\'accueil : ce qu\'il y a à faire aujourd\'hui, en quatre chiffres puis en listes.',
             '첫 화면이에요. 오늘 할 일을 숫자 네 개와 목록으로 보여 줘요.'],
            ['- Les chiffres : <b>À répondre</b> (avec le nombre en retard), <b>Retraits à venir</b> sur 7 jours, les <b>Remises du mois</b> avec le total encaissé, et le <b>Panier moyen des particuliers</b> (les livraisons aux restaurants n\'y entrent pas).',
             '- 숫자: <b>답장 필요</b>(늦은 건수 포함), 7일 동안의 <b>다가오는 픽업</b>, 이달 <b>전달 완료</b>와 총액, <b>개인 평균 주문 금액</b>(레스토랑 배달은 빼고 계산해요).'],
            ['- Les listes : <b>Retraits passés à clôturer</b> (à marquer remises ou annulées), <b>Problèmes ouverts</b>, <b>Demandes à répondre</b>, <b>Retraits des 7 prochains jours</b> (jour par jour), <b>Propositions en attente de réponse du client</b>, <b>Acomptes attendus</b> et, pour les restaurants, <b>Relevés à facturer</b>.',
             '- 목록: <b>마무리할 지난 픽업</b>(전달 완료나 취소로 표시), <b>해결 안 된 문제</b>, <b>답장할 문의</b>, <b>앞으로 7일 동안의 픽업</b>(날짜별), <b>고객 답장을 기다리는 견적</b>, <b>받아야 할 선금</b>, 그리고 레스토랑의 <b>청구할 내역서</b>.'],
            ['- Le bouton <b>Fiche de production</b> ouvre la liste à imprimer pour l\'atelier.',
             '- <b>생산 시트</b> 버튼은 작업실용 인쇄 목록을 열어요.'],
            ['Une liste vide est une bonne nouvelle : il n\'y a rien à faire de ce côté-là.',
             '목록이 비어 있으면 좋은 소식이에요. 그쪽에는 할 일이 없다는 뜻이에요.'],
            ['Partout, chaque page et chaque cadre a devant son titre l\'icône de sa rubrique, la même que dans le menu : on sait d\'un coup d\'œil où l\'on est.',
             '어느 화면이든 제목 앞에 메뉴와 같은 아이콘이 있어요. 지금 어디에 있는지 한눈에 알 수 있어요.'],
        ]],
        ['id' => 'pastilles', 'titre' => ['Les pastilles : ce qui est nouveau', '색깔 표시: 새로 생긴 것'], 'textes' => [
            ['De petites pastilles de couleur montrent ce qui est arrivé ou a changé <b>depuis votre dernière visite</b>. Chaque personne a les siennes : ce qu\'Olivier a ouvert reste nouveau pour vous.',
             '작은 색깔 표시가 <b>마지막 방문 이후</b> 새로 오거나 바뀐 것을 알려 줘요. 사람마다 따로예요. Olivier가 연 것도 나에게는 계속 새것으로 보여요.'],
            ['- <b>Rouge, avec un chiffre</b> : à regarder en premier. Une nouvelle demande (site ou QR code), une réponse d\'un client, un problème signalé. Le chiffre est sur l\'onglet « Commandes », dans l\'onglet du navigateur et, si votre téléphone le permet, sur l\'icône de l\'application (mise à jour à la prochaine ouverture de la gestion). La pastille s\'éteint quand vous ouvrez la commande, même si vous n\'avez pas encore répondu : les listes « À répondre » d\'Aujourd\'hui restent votre liste de tâches.',
             '- <b>빨간색과 숫자</b>: 먼저 볼 것이에요. 새 문의(사이트나 QR 코드), 고객 답장, 신고된 문제. 숫자는 ‘주문’ 탭, 브라우저 탭, 그리고 휴대폰이 지원하면 앱 아이콘에 나와요(관리 화면을 다시 열 때 바뀌어요). 아직 답장하지 않았어도 주문을 열면 표시가 사라져요. 할 일 목록은 오늘 화면의 ‘답장 필요’ 목록이에요.'],
            ['- <b>Ambre, un simple point</b> : quelque chose a changé, fait par quelqu\'un d\'autre : étape, paiement, Qonto, modification, note, mail envoyé, fiche client créée par le site ou modifiée.',
             '- <b>주황색 점</b>: 다른 사람이 무언가를 바꿨어요. 단계, 결제, Qonto, 수정, 메모, 보낸 메일, 사이트로 생긴 고객 정보나 고친 고객 정보.'],
            ['- <b>Chocolat ✦</b> : une nouveauté de l\'outil. Elle est expliquée dans Plus › « Quoi de neuf ».',
             '- <b>초콜릿색 ✦</b>: 관리 도구의 새 기능이에요. 더보기 › ‘새 소식’에 설명이 있어요.'],
            ['Sur <b>Aujourd\'hui</b>, le bloc « Depuis votre dernière visite » les liste, les rouges d\'abord. Dans une commande, l\'encadré « Changé depuis votre dernière visite » montre l\'avant et l\'après, et qui l\'a fait.',
             '<b>오늘</b> 화면의 ‘마지막 방문 이후’ 블록에 빨간 것부터 모여 있어요. 주문을 열면 ‘마지막 방문 이후 바뀐 내용’ 상자에 전과 후, 바꾼 사람이 나와요.'],
            ['Une pastille <b>s\'éteint</b> quand vous ouvrez la commande ou la fiche. « Tout marquer comme vu » les éteint toutes, pour vous seule. Ce que vous faites vous-même n\'est jamais signalé, et une pastille s\'éteint seule au bout de 30 jours.',
             '주문이나 고객 정보를 열면 표시가 <b>사라져요</b>. ‘모두 확인함으로 표시’를 누르면 나에게서만 모두 사라져요. 내가 직접 한 일은 표시되지 않고, 30일이 지나면 저절로 사라져요.'],
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
            ['- Ouverte depuis les Statistiques, la liste est <b>filtrée</b> (étape, canal, mois, particuliers ou professionnels) : le filtre est écrit en haut, et « Retirer le filtre » revient à la liste complète.',
             '- 통계에서 열면 목록이 <b>필터</b>된 상태예요(단계, 경로, 월, 개인 또는 업체). 위쪽에 필터가 보이고, ‘필터 지우기’를 누르면 전체 목록으로 돌아가요.'],
        ]],
        ['id' => 'commande', 'titre' => ['Une commande', '주문 하나'], 'textes' => [
            ['L\'écran d\'une commande montre le <b>client</b> (avec un lien vers sa fiche), le <b>contenu</b> (gâteaux, tailles, parfums, plaque en chocolat, personnalisation), le <b>montant et le paiement</b>, puis les boutons de l\'étape en cours.',
             '주문 화면에는 <b>고객</b>(고객 정보 링크 포함), <b>내용</b>(케이크, 크기, 맛, 초콜릿 플레이트, 맞춤 내용), <b>금액과 결제</b>, 그리고 지금 단계의 버튼이 있어요.'],
            ['En haut, la <b>frise des étapes</b> reste toujours visible : demande reçue, proposition envoyée, commande confirmée, confirmation envoyée au client, remise au client, avis demandé. Les étapes faites ont une coche, l\'étape à faire est marquée « À faire maintenant ». « Modifier la commande » est en haut à droite ; après un changement, le bandeau vert dit ce qui a changé (par exemple « heure du retrait : 09 h 00 → 10 h 00 »). « Confirmer la commande » est aussi sous les échanges.',
             '위쪽의 <b>단계 표시</b>는 항상 보여요: 요청 받음, 견적 발송, 주문 확정, 고객에게 확정 이메일 보냄, 고객에게 전달, 리뷰 부탁함. 끝난 단계에는 체크가 있고, 할 단계에는 ‘지금 할 일’이 붙어요. ‘주문 수정’은 오른쪽 위에 있어요. 수정한 뒤 초록 안내에 바뀐 내용이 나와요(예: ‘픽업 시간: 09:00 → 10:00’). ‘주문 확정’ 버튼은 연락 기록 아래에도 있어요.'],
            ['- <b>À répondre</b> : « Envoyer la proposition au client » (prix, acompte, aperçu, « Envoyer »). Ou « Classer sans suite ».',
             '- <b>답장 필요</b>: ‘고객에게 견적 이메일 보내기’(가격, 선금, 미리보기, ‘보내기’). 또는 ‘진행 안 됨으로 정리’.'],
            ['- <b>Proposition</b> : « Confirmer la commande » avec la date et l\'heure du retrait, ou « Classer sans suite » si le client ne répond plus.',
             '- <b>견적</b>: ‘주문 확정’을 누르고 픽업 날짜와 시간을 적어요. 고객이 더 답하지 않으면 ‘진행 안 됨으로 정리’.'],
            ['- <b>Confirmée</b> : « Commande remise au client » le jour du retrait, avec le paiement (payé, acompte, moyen). Un lien ajoute le retrait à l\'agenda.',
             '- <b>확정</b>: 픽업 날 ‘고객에게 전달 완료’를 누르고 결제(완료, 선금, 결제 수단)를 적어요. 링크로 픽업을 캘린더에 넣을 수 있어요.'],
            ['- <b>Remise</b> : « Demander un avis Google » (mail de remerciement, « Envoyer »), puis « L\'avis est arrivé ».',
             '- <b>전달 완료</b>: ‘구글 리뷰 부탁하기’(감사 이메일, ‘보내기’), 리뷰가 올라오면 ‘리뷰가 올라왔어요’.'],
            ['- <b>Autres actions</b> : « Modifier » (contenu, retrait, prix), « Faire une copie (client régulier) » pour une commande qui revient, « Changer l\'étape à la main », « Modifier le paiement », « Annuler la commande » (par le client ou par Madame Cho, avec le motif), « Rouvrir la demande ». « Supprimer la commande » est réservé aux essais et aux erreurs de saisie (jamais une commande facturée, rapprochée avec Qonto ou notée payée) : tapez SUPPRIMER ; une fiche client sans commande se supprime de la même façon. Ce qui est supprimé va dans <b>Plus › Corbeille</b> : 30 jours pour le restaurer d\'un toucher sur « Restaurer », puis il s\'efface tout seul.',
             '- <b>다른 작업</b>: ‘수정’(내용, 픽업, 가격), ‘복사해서 새 주문 (단골 고객)’, ‘단계 직접 바꾸기’, ‘결제 수정’, ‘주문 취소’(고객 요청 또는 Madame Cho 결정, 이유와 함께), ‘문의 다시 열기’. ‘주문 삭제’는 테스트나 입력 실수에만 써요(청구서가 있거나 Qonto와 대조됐거나 결제됨으로 적힌 주문은 안 돼요): SUPPRIMER를 입력해요. 주문이 없는 고객 정보도 같은 방법으로 삭제해요. 삭제한 것은 <b>더보기 › 휴지통</b>으로 가요. 30일 안에 ‘되살리기’를 눌러 되살릴 수 있고, 그 뒤에는 저절로 지워져요.'],
            ['- <b>Signaler un problème</b> : un retard, une réclamation, un paiement qui n\'arrive pas. Le problème reste visible sur Aujourd\'hui et dans Commandes › Problèmes jusqu\'à « Marquer résolu ».',
             '- <b>문제 신고</b>: 지연, 불만, 안 들어오는 결제. ‘해결됨으로 표시’를 누를 때까지 오늘 화면과 주문 › 문제 탭에 계속 보여요.'],
            ['- <b>Échanges avec le client</b> : toute la conversation de la commande, comme une messagerie : le plus récent en haut, le client à gauche, Madame Cho à droite, les échanges notés au milieu ; ce que vous n\'avez pas encore vu est en haut sous le trait « Nouveaux messages », puis le trait « Déjà vus ». On y trouve les mails envoyés depuis la gestion, les <b>réponses du client</b>, arrivées toutes seules depuis contact@ (« Nouvelle réponse » tant que vous ne les avez pas vues, aussi sur Aujourd\'hui et dans la liste des commandes ; « Préparer une réponse » ouvre « Écrire au client », rien ne part avant « Envoyer »), vos mails envoyés depuis Gmail, et les échanges notés à la main. « Écrire au client » envoie un autre mail sur cette commande ; le client voit tous les mails de la commande dans une seule conversation. « Noter un échange » garde ce qui ne passe pas par les mails (téléphone, SMS, WhatsApp, de vive voix…), avec « Par ». Sous chaque zone de texte : « Dicter » (vous parlez, le texte s\'écrit) et « Corriger mon français » (l\'assistant propose un texte corrigé, avec le sens en coréen ; « Utiliser ce texte » ou « Garder le mien »). Rien n\'est enregistré ni envoyé avant votre toucher sur « Ajouter » ou « Envoyer ». « Ses emails dans Gmail » reste le secours : l\'original de chaque mail y est toujours.',
             '- <b>고객과의 연락</b>: 이 주문의 대화 전체가 메신저처럼 보여요. 맨 위가 가장 최근 것이고, 고객은 왼쪽, Madame Cho는 오른쪽, 직접 적은 연락은 가운데에 있어요. 아직 안 본 것은 맨 위 ‘새 메시지’ 선 아래에 있고, 그다음 ‘이미 본 메시지’ 선이 있어요. 관리에서 보낸 이메일, contact@로 온 <b>고객 답장</b>(자동으로 들어와요; 보기 전까지 ‘새 답장’ 표시가 오늘 화면과 주문 목록에도 보이고, ‘답장 준비하기’를 누르면 ‘고객에게 이메일 쓰기’가 열려요. ‘보내기’를 누르기 전에는 아무것도 보내지 않아요), Gmail에서 보낸 이메일, 직접 적은 연락 내용이 있어요. ‘고객에게 이메일 쓰기’로 이 주문에 대해 다른 이메일을 보내요. 고객은 이 주문의 이메일을 모두 한 대화로 받아요. ‘연락 내용 적기’에는 이메일이 아닌 연락(전화, 문자, 왓츠앱, 직접 대화…)을 ‘경로’와 함께 적어요. 글 쓰는 칸 아래에는 ‘말로 입력’(말하면 글이 써져요)과 ‘프랑스어 고치기’(어시스턴트가 고친 프랑스어와 한국어 뜻을 보여 줘요; ‘이 문장 쓰기’ 또는 ‘내 문장 그대로’)가 있어요. ‘추가’나 ‘보내기’를 누르기 전에는 아무것도 저장되거나 나가지 않아요. ‘Gmail에서 이메일 보기’는 예비 수단이에요: 모든 이메일의 원본은 항상 거기 있어요.'],
            ['- <b>Expéditeur différent du client : à vérifier</b> : une réponse arrivée d\'une autre adresse que celle de la fiche (un conjoint, une adresse du travail…) est quand même rangée avec la commande, mais marquée de ce petit drapeau orange. Lisez-la avant d\'y donner suite ; si c\'est bien le client, rien à faire de plus.',
             '- <b>Expéditeur différent du client : à vérifier</b>: 고객 정보와 다른 주소(배우자, 회사 주소 등)에서 온 답장도 주문에 들어가지만, 작은 주황색 표시가 붙어요. 처리하기 전에 꼭 읽어 보세요. 정말 그 고객이면 더 할 일은 없어요.'],
            ['- Un mail touché deux fois par erreur ne part pas deux fois : si le même mail est déjà parti il y a moins de 2 minutes, le bandeau le dit et rien n\'est renvoyé. Si un envoi échoue, regardez d\'abord dans les Échanges ou dans Gmail (Envoyés) avant de réessayer.',
             '- 실수로 두 번 눌러도 같은 이메일이 두 번 나가지 않아요. 2분 안에 같은 이메일이 이미 나갔으면 안내가 나오고 다시 보내지 않아요. 보내기가 실패하면 다시 하기 전에 먼저 연락 기록이나 Gmail(보낸편지함)을 확인하세요.'],
            ['Les mails aux clients sont en français, partent de contact@madamecho.com seulement quand vous touchez « Envoyer » (jamais tout seuls, jamais par l\'assistant), et une copie arrive dans contact@. Ils ont tous le même style ; une petite ligne en haut dit ce que c\'est : PROPOSITION, COMMANDE CONFIRMÉE, RAPPEL, MESSAGE ou MERCI.',
             '고객에게 가는 이메일은 프랑스어이고, ‘보내기’를 눌러야만 contact@madamecho.com에서 나가요(저절로 나가거나 어시스턴트가 보내는 일은 없어요). 사본이 contact@로 와요. 모두 같은 디자인이고, 맨 위 작은 줄에 종류가 적혀 있어요: PROPOSITION(견적), COMMANDE CONFIRMÉE(주문 확정), RAPPEL(픽업 알림), MESSAGE(메시지), MERCI(감사).'],
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
        ['id' => 'agenda', 'titre' => ['Agenda et indispos', '일정과 예약 불가'], 'textes' => [
            ['L\'<b>Agenda</b> montre le mois, jour par jour : les retraits et les livraisons (demandes, propositions, confirmées, remises) et vos <b>indispos</b>. Les flèches changent de mois, « Aujourd\'hui » revient au jour même.',
             '<b>일정</b>은 한 달을 날짜별로 보여 줘요. 픽업과 배달(문의, 견적, 확정, 전달 완료)과 <b>예약 불가</b>가 있어요. 화살표로 달을 바꾸고, ‘오늘’을 누르면 오늘로 돌아와요.'],
            ['- Sur le téléphone, chaque commande est un point de couleur ; sur l\'ordinateur, l\'heure et le client. Touchez un jour : sa liste s\'ouvre dessous, et chaque commande mène à sa page.',
             '- 휴대폰에서는 주문마다 색깔 점이 하나씩, 컴퓨터에서는 시간과 고객 이름이 보여요. 날짜를 누르면 아래에 목록이 열리고, 주문을 누르면 주문 화면으로 가요.'],
            ['- Une <b>indispo</b>, c\'est un jour entier ou une plage horaire (de 14 h à 18 h, par exemple), sur un ou plusieurs jours : vacances, salon, journée déjà complète. « Ajouter une indispo », puis « Modifier » ou « Supprimer cette indispo » dans la liste <b>Indispos à venir</b>.',
             '- <b>예약 불가</b>는 하루 종일 또는 시간대(예: 14시~18시)로, 하루나 여러 날에 걸쳐 정해요. 휴가, 박람회, 이미 꽉 찬 날 같은 경우예요. ‘예약 불가 추가’를 누르고, <b>다가오는 예약 불가</b> 목록에서 ‘수정’하거나 ‘이 예약 불가 삭제’를 눌러요.'],
            ['- Si une commande en cours tombe sur une indispo, la gestion prévient : en ajoutant l\'indispo, en confirmant la commande, et sur la page de la commande. À voir avec le client, ou changer la date.',
             '- 진행 중인 주문이 예약 불가와 겹치면 관리 화면이 알려 줘요. 예약 불가를 추가할 때, 주문을 확정할 때, 그리고 주문 화면에서요. 고객과 상의하거나 날짜를 바꾸세요.'],
            ['- Le formulaire « Ma demande » du site en tient compte : le client qui choisit un jour ou un moment indisponible le voit tout de suite, et sa demande ne part pas tant qu\'il n\'a pas changé. Le site ne dit jamais le motif.',
             '- 사이트의 ‘내 문의’ 양식에도 반영돼요. 고객이 예약 불가인 날짜나 시간대를 고르면 바로 안내가 보이고, 바꾸기 전에는 문의가 보내지지 않아요. 사이트에는 이유가 절대 보이지 않아요.'],
            ['- Sur le site, une plage ferme tout le moment qu\'elle touche : une indispo de 15 h à 16 h ferme l\'après-midi (14 h – 18 h). Les autres moments du jour restent possibles.',
             '- 사이트에서는 예약 불가 시간대가 걸친 시간대 전체가 막혀요. 예를 들어 15시~16시 예약 불가는 오후(14시~18시) 전체를 막아요. 그날의 다른 시간대는 그대로 고를 수 있어요.'],
        ]],
        ['id' => 'clients', 'titre' => ['Clients', '고객'], 'textes' => [
            ['La liste des clients, avec la recherche par nom, email, téléphone ou ville. Deux groupes bien séparés : les <b>professionnels (B2B)</b>, restaurants et entreprises, comptés en HT comme leurs factures ; les <b>particuliers (B2C)</b>, comptés en TTC. Chaque fiche porte son étiquette « Pro · B2B » (violette) ou « Particulier · B2C » (bleu-vert) : les mêmes couleurs partout, dans les listes, les fiches et les statistiques.',
             '고객 목록이에요. 이름, 이메일, 전화번호, 도시로 검색해요. 두 그룹으로 나뉘어요: <b>업체 (B2B)</b>는 레스토랑과 회사로, 청구서처럼 부가세 별도로 계산해요. <b>개인 (B2C)</b>은 부가세 포함으로 계산해요. 고객마다 ‘업체 · B2B’(보라색) 또는 ‘개인 · B2C’(청록색) 표시가 있어요. 목록, 고객 정보, 통계 어디서나 같은 색이에요.'],
            ['- La <b>fiche</b> d\'un client : ses <b>coordonnées</b>, ses <b>commandes et demandes</b>, l\'<b>avis Google</b> (demandé, reçu), <b>Tous les échanges</b> et <b>Notes et historique</b>.',
             '- 고객 <b>정보</b>: <b>연락처</b>, <b>주문과 문의</b>, <b>구글 리뷰</b>(요청함, 받음), <b>모든 연락</b>, <b>메모와 기록</b>.'],
            ['- <b>Tous les échanges</b> : tous les mails envoyés et reçus et les échanges notés avec ce client, toutes commandes confondues, le plus récent en haut, comme dans une commande. Sous chaque message, le numéro MC-… ouvre sa commande.',
             '- <b>모든 연락</b>: 이 고객과 주고받은 이메일과 직접 적은 연락을 주문 구분 없이 모두 보여 줘요. 주문 화면처럼 맨 위가 가장 최근 것이에요. 메시지마다 아래의 MC-… 번호를 누르면 그 주문이 열려요.'],
            ['- <b>Notes et historique</b> : vos notes sur le client (goûts, habitudes, retrait préféré) ; dessous, « Journal détaillé » replié garde tout ce qui s\'est passé (étapes, paiements, modifications).',
             '- <b>메모와 기록</b>: 고객에 대한 메모(취향, 습관, 선호하는 픽업). 아래 접힌 ‘자세한 기록’에 있었던 일(단계, 결제, 수정)이 모두 남아 있어요.'],
            ['- <b>Corriger un nom</b> : sur la fiche du client (ou dans le cadre Client d\'une commande), « Corriger le nom ou les coordonnées ». Les majuscules oubliées (« jean dupont ») sont ajoutées toutes seules, dans la gestion comme sur le formulaire du site ; un nom écrit exprès autrement (« McDonald », « de Gaulle ») est gardé tel quel.',
             '- <b>이름 고치기</b>: 고객 정보(또는 주문의 고객 칸)에서 ‘이름이나 연락처 고치기’를 눌러요. 잊어버린 대문자(« jean dupont »)는 관리 시스템과 사이트 양식에서 저절로 붙어요. 일부러 다르게 적은 이름(« McDonald », « de Gaulle »)은 그대로 둬요.'],
            ['- <b>Fiche et droits du client</b> : « Corriger le nom ou les coordonnées » ; si le client demande ses données (« Demande d\'accès à ses données »), « Télécharger ses données » donne le fichier à lui envoyer depuis contact@ dans le mois ; s\'il demande l\'effacement (« Demande d\'effacement »), « Effacer ses données » garde seulement le numéro, les dates et les montants.',
             '- <b>고객 정보와 권리</b>: ‘이름이나 연락처 고치기’로 정보를 고쳐요. 고객이 자기 정보를 요청하면 ‘고객 정보 다운로드’로 받은 파일을 한 달 안에 contact@에서 보내요. 삭제를 요청하면 ‘고객 정보 삭제’로 번호, 날짜, 금액만 남겨요.'],
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
            ['- <b>Recettes, coûts et marges</b> : une recette par taille ou parfum, avec les ingrédients, le coût matières et la marge sur matières (le travail, l\'énergie et les pertes ne sont pas comptés). « Copier une recette » vers une autre taille ou un autre parfum évite de tout ressaisir.',
             '- <b>레시피, 원가, 마진</b>: 크기나 맛마다 레시피 하나, 재료, 재료비, 재료 마진(인건비, 에너지, 손실은 포함되지 않아요). ‘레시피 복사’로 다른 크기나 맛에 옮기면 다시 입력하지 않아도 돼요.'],
            ['- <b>Ventes des 12 derniers mois</b> : les quantités par taille ou parfum, aux <b>particuliers (B2C)</b> et aux <b>pros (B2B)</b> séparément, avec les montants tous en HT ou tous en TTC (le choix <b>Montants HT | TTC</b> au-dessus du tableau, le même que dans Statistiques), et depuis quand le gâteau est proposé. Cette date se note dans la fiche technique (« Proposé depuis le ») ; sans elle, la gestion prend la première demande qui contient le gâteau. Puis l\'<b>Historique</b> des modifications.',
             '- <b>최근 12개월 판매</b>: 크기나 맛별 수량을 <b>개인 (B2C)</b>과 <b>업체 (B2B)</b>로 나눠서 보여 주고, 금액은 모두 부가세 별도 또는 모두 부가세 포함이에요 (표 위의 <b>금액: 부가세 별도 | 부가세 포함</b> 선택, 통계와 같아요). 언제부터 판매했는지도 보여 줘요. 이 날짜는 기술 시트의 ‘판매 시작일’에 적어요. 적지 않으면 이 케이크가 들어간 첫 문의 날짜를 써요. 그리고 수정 <b>기록</b>.'],
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
        ['id' => 'rapprochement', 'titre' => ['Rapprochement Qonto', 'Qonto 대조'], 'textes' => [
            ['« Payé » et « retrouvé dans Qonto » sont deux choses séparées. « Payé » : vous avez constaté le paiement (Tap to Pay, virement, espèces) ; la commande garde qui l\'a noté et quand. « Rapproché » : vous avez retrouvé ce paiement dans Qonto et noté la référence de l\'opération.',
             '‘결제 완료’와 ‘Qonto에서 확인’은 서로 달라요. ‘결제 완료’: 결제(Tap to Pay, 계좌 이체, 현금)를 직접 확인한 거예요. 주문에 누가 언제 적었는지 남아요. ‘대조 완료’: 그 결제를 Qonto에서 찾아서 거래 참조 번호를 적은 거예요.'],
            ['- <b>Plus › Rapprochement Qonto</b> : les paiements par carte ou virement pas encore retrouvés dans Qonto, avec le montant à chercher. Cochez le ou les paiements qu\'une opération Qonto regroupe (un versement Tap to Pay peut en réunir plusieurs), collez la référence de l\'opération, puis « Noter rapprochés ». Un nom ou un montant seuls ne suffisent pas.',
             '- <b>더보기 › Qonto 대조</b>: 카드나 계좌 이체로 받았지만 아직 Qonto에서 확인하지 않은 결제와 찾을 금액이 보여요. Qonto 거래 하나에 들어 있는 결제를 체크하고(Tap to Pay 입금 하나에 여러 건이 들어 있을 수 있어요), 거래 참조 번호를 붙여 넣은 다음 ‘대조 완료로 표시’를 눌러요. 이름이나 금액만으로는 안 돼요.'],
            ['- Dans une commande, <b>Montant et paiement</b> montre « Constaté par » et l\'état Qonto ; « Rapprocher avec Qonto » fait la même chose pour une seule commande, « Retirer le rapprochement » corrige une erreur. Les espèces n\'ont pas de rapprochement. Si le paiement change, le rapprochement est à refaire.',
             '- 주문 화면의 <b>금액과 결제</b>에 ‘확인한 사람’과 Qonto 상태가 보여요. ‘Qonto와 대조’는 주문 하나만 같은 방식으로 처리하고, ‘대조 취소’로 잘못 적은 것을 고쳐요. 현금은 대조하지 않아요. 결제 내용이 바뀌면 다시 대조해야 해요.'],
            ['Qonto n\'est jamais modifié depuis la gestion, et un versement Qonto ne crée jamais de vente ni de paiement. L\'assistant peut dire ce qui reste à rapprocher, mais il ne note pas de rapprochement.',
             '관리 화면에서 Qonto를 바꾸는 일은 절대 없고, Qonto 입금으로 판매나 결제가 새로 생기지도 않아요. 어시스턴트는 대조할 것이 남았는지 알려 줄 수 있지만, 대조를 직접 적지는 않아요.'],
        ]],
        ['id' => 'corbeille', 'titre' => ['Corbeille', '휴지통'], 'textes' => [
            ['<b>Plus › Corbeille</b> garde 30 jours ce qui a été supprimé par erreur : une commande d\'essai (avec ses échanges) ou une fiche client sans commande. Chaque ligne dit quand elle a été supprimée, par qui, et dans combien de jours elle s\'efface.',
             '<b>더보기 › 휴지통</b>에는 실수로 삭제한 것이 30일 동안 남아요. 테스트 주문(연락 기록 포함)이나 주문이 없는 고객 정보요. 줄마다 언제, 누가 삭제했는지, 며칠 뒤에 지워지는지 적혀 있어요.'],
            ['- <b>Restaurer</b> la remet à sa place, telle qu\'elle était. Pour une commande dont la fiche client est aussi à la corbeille, restaurez d\'abord la fiche.',
             '- <b>되살리기</b>를 누르면 원래 자리로 그대로 돌아가요. 고객 정보도 휴지통에 있는 주문이면 고객 정보부터 되살려요.'],
            ['- Après 30 jours, elle s\'efface toute seule, la nuit. Une demande d\'effacement d\'un client (RGPD) vide aussi sa part de la corbeille tout de suite.',
             '- 30일이 지나면 밤에 저절로 지워져요. 고객이 정보 삭제를 요청하면(GDPR) 휴지통에 있는 그 고객의 것도 바로 지워져요.'],
        ]],
        ['id' => 'obligations', 'titre' => ['Obligations', '의무'], 'textes' => [
            ['Plus › <b>Obligations</b> rassemble tout ce qu\'il faut faire ou prouver pour être en règle : déclarations à la DDPP, assurance, vérification de l\'extincteur, médiateur de la consommation, impôts… Le plus urgent est en haut.',
             '더보기 › <b>의무</b>에는 규정을 지키기 위해 해야 하거나 증명해야 하는 일이 모두 모여 있어요. DDPP 신고, 보험, 소화기 점검, 소비자 중재인, 세금 등이에요. 가장 급한 일이 맨 위에 있어요.'],
            ['- Chaque démarche dit <b>quoi faire</b>, <b>où</b> (l\'outil, la page, l\'endroit), <b>pour quand</b> et <b>pourquoi</b>. L\'état est toujours écrit : « En retard depuis le … » (rouge), « Avant le … » (rouge, dans son rappel), « Envoyé, réponse attendue » ou « Date à confirmer » (ambre), « Pour le … » (plus tard).',
             '- 할 일마다 <b>무엇을</b>, <b>어디서</b>(도구, 페이지, 위치), <b>언제까지</b>, <b>왜</b> 하는지 적혀 있어요. 상태는 늘 글로 보여요: ‘…부터 늦음’(빨강), ‘… 전까지’(빨강, 알림 기간), ‘보냄, 답 기다림’이나 ‘날짜 확인 필요’(주황), ‘…까지’(나중에).'],
            ['- <b>Les miennes</b> montre vos démarches et celles pour vous deux ; <b>Toutes</b> montre aussi celles d\'Olivier. Vous pouvez tous les deux ajouter et modifier une démarche ; un point ambre signale ce que l\'autre a ajouté ou changé.',
             '- <b>내 일</b>에는 내 일과 두 사람 모두의 일이 나와요. <b>전체</b>에는 올리비에의 일도 나와요. 두 사람 모두 할 일을 추가하고 고칠 수 있어요. 상대가 추가하거나 바꾼 것은 주황 점으로 표시돼요.'],
            ['- Quand c\'est réglé : rangez la preuve (récépissé, attestation, rapport) dans le Drive, copiez son lien, puis « C\'est fait ». Une démarche <b>importante</b> ne se clôt qu\'avec ce lien. Envoyé mais pas encore de réponse ? « Envoyé, j\'attends la réponse ». Plus utile ? « Sans objet », avec la raison. Rien n\'est effacé : la démarche passe dans « Fait », avec son historique.',
             '- 끝나면 증빙(접수증, 증명서, 보고서)을 Drive에 넣고 그 링크를 복사한 뒤 ‘완료’를 눌러요. <b>중요한</b> 일은 이 링크가 있어야만 닫을 수 있어요. 보냈는데 아직 답이 없으면 ‘보냄, 답 기다림’. 더 이상 필요 없으면 이유를 적고 ‘해당 없음’. 지워지는 건 없어요. 기록과 함께 ‘완료’로 옮겨져요.'],
            ['- <b>Échéances</b> : ce qui revient (chaque année, chaque trimestre, chaque mois, le premier lundi du mois). Chaque échéance crée sa démarche quand son rappel commence (30 jours avant, ou le délai choisi), une fois par période. Une date inconnue reste « à confirmer » : elle n\'est jamais devinée.',
             '- <b>정기 일정</b>: 되풀이되는 일(매년, 분기마다, 매달, 매달 첫 월요일)이에요. 알림이 시작되면(30일 전, 또는 정한 기간) 기간마다 한 번씩 할 일이 생겨요. 모르는 날짜는 ‘날짜 확인 필요’로 남고, 절대 추측하지 않아요.'],
            ['- <b>Nouveau client professionnel</b> : dès qu\'un client est professionnel (sa fiche ou sa première demande), une démarche importante demande de vérifier les obligations avant de confirmer ou de livrer : ce qu\'il fait des gâteaux, les produits, les quantités, la distance, la dérogation sanitaire (Cerfa 13982) si besoin, la facture. Ce n\'est pas toujours une démarche de plus : s\'il n\'y a rien à faire, « Sans objet » avec la raison et sa source. Ensuite, un gâteau qu\'il n\'a jamais commandé, un gâteau sur mesure ou une première livraison redemandent une vérification, même si la commande est modifiée après confirmation. La fiche montre le client concerné.',
             '- <b>새 사업자 고객</b>: 고객이 사업자가 되면(고객 정보나 첫 요청), 확정하거나 배달하기 전에 의무를 확인하라는 중요한 할 일이 생겨요. 케이크를 어떻게 쓰는지, 제품, 수량, 거리, 필요하면 위생 승인 면제(Cerfa 13982), 청구서를 확인해요. 늘 새 신청이 필요한 건 아니에요. 할 일이 없으면 이유와 출처를 적고 ‘해당 없음’. 그 뒤에는 처음 주문하는 케이크, 맞춤 케이크, 첫 배달마다 다시 확인을 요청해요. 확정 뒤에 주문을 고쳐도 마찬가지예요. 할 일 정보에 해당 고객이 나와요.'],
            ['- Une démarche envoyée qui attend une réponse reste dans « En attente » ; si son échéance passe, c\'est écrit en rouge. Modifier une échéance met aussi à jour ses démarches ouvertes ; si sa date change, celles d\'avant restent ouvertes avec « Date d\'avant la modification » : à vous de les garder ou de les clore.',
             '- 보내고 답을 기다리는 할 일은 ‘답을 기다리는 중’에 남아요. 기한이 지나면 빨간 글씨로 보여요. 정기 일정을 고치면 열린 할 일도 함께 바뀌어요. 날짜가 바뀌면 전의 할 일은 ‘수정 전 날짜’로 남고, 두거나 닫는 것은 직접 정해요.'],
            ['- Chaque lundi, la gestion envoie à contact@ un court mail interne « Obligations de la semaine » (ce qui est en retard ou à faire bientôt), repris dans le récap du lundi. Il ne part jamais à un client et ne contient aucun texte tapé : chaque démarche y figure par son numéro, avec sa date et pour qui (seules les vérifications écrites par la gestion gardent leur titre). Tant que la liste de départ n\'est pas reprise et relue, il le dit. S\'il n\'a pas pu partir, Paramètres › Conservation le montre, avec un bouton pour le renvoyer.',
             '- 매주 월요일, 관리 화면이 contact@로 짧은 내부 이메일 ‘이번 주 의무’(늦었거나 곧 할 일)를 보내고, 월요일 요약에 들어가요. 고객에게는 절대 가지 않고 직접 적은 글은 들어가지 않아요. 할 일은 번호, 날짜, 누구의 일로만 나와요(관리 화면이 쓴 확인 할 일만 제목이 나와요). 처음 목록을 아직 넣거나 읽지 않았으면 그렇다고 적혀요. 보내지 못했으면 설정 › 보관에 보이고, 다시 보내기 버튼이 있어요.'],
            ['- L\'écran est réservé à Youngmi et à Olivier (son compte administrateur) : un autre compte de la gestion, même administrateur, ne le voit pas.',
             '- 이 화면은 Youngmi와 Olivier만 볼 수 있어요. 관리 화면의 다른 계정에는, 관리자라도, 보이지 않아요.'],
            ['- Dans les textes, jamais de nom ni de donnée d\'un client particulier. Les démarches closes sont effacées 10 ans après.',
             '- 글에는 개인 고객의 이름이나 정보를 절대 적지 마세요. 닫힌 할 일은 10년 뒤에 지워져요.'],
            ['- <b>Volumes</b> : chaque semaine, les kilos vendus aux restaurants et à tous, la part des restaurants et le plafond de la dérogation à l\'agrément (Cerfa 13982). Les kilos viennent du <b>poids d\'une pièce</b> de chaque gâteau : la recette en propose un, pesez de préférence une pièce finie et touchez « Confirmer » ; sans poids, la gestion dit « kilos incomplets » et ne devine rien. Les plafonds sont préremplis avec la réglementation et marqués « à confirmer » tant que la DDPP ne les a pas confirmés (Volumes › Plafonds de la dérogation). Tapez aussi la distance de chaque restaurant. Quand les restaurants approchent ou dépassent le plafond, une démarche apparaît dans « À faire » (elle devient importante si le plafond est dépassé). Tant que la DDPP n\'a pas confirmé les plafonds, une semaine sous le plafond reste « Non vérifié » ; le plafond se choisit d\'après la part des restaurants en kilos, jamais en euros (le plafond réduit s\'applique tant que les kilos sont incomplets). Une commande sur mesure se pèse dans « Commandes sur mesure ». Un restaurant sans distance, ou trop loin, avec une commande dans les 14 jours : une démarche le rappelle ; la commande le signale aussi, et le départ de la livraison ne se note qu\'en cochant « Je livre quand même », avec la raison dans la note. Attention : la réglementation fixe un plafond <b>par catégorie de produits</b>, et la gestion fait pour l\'instant un seul total pour tous les gâteaux ; « Sous le seuil saisi » veut seulement dire sous le chiffre tapé, pas « en règle ». Demandez à la DDPP dans quelle catégorie vont vos gâteaux.',
             '- <b>물량</b>: 주마다 레스토랑과 전체에 판 킬로그램, 레스토랑 비율, 위생 승인 면제(Cerfa 13982)의 한도가 나와요. 킬로그램은 케이크마다 <b>한 개의 무게</b>로 계산해요. 레시피가 무게를 제안하지만, 가능하면 완성된 케이크 한 개를 재고 ‘확인’을 누르세요. 무게가 없으면 ‘킬로그램이 불완전해요’라고 나오고, 절대 짐작하지 않아요. 한도는 규정 기준으로 미리 넣었고, DDPP가 확인할 때까지 ‘확인 필요’로 표시돼요(물량 › 면제 한도). 레스토랑마다 거리도 적어 주세요. 레스토랑 물량이 한도에 가깝거나 넘으면 ‘할 일’에 생겨요(넘으면 중요한 일로 바뀌어요). DDPP가 한도를 확인할 때까지 한도 아래인 주도 ‘확인 안 됨’이에요. 한도는 금액이 아니라 킬로그램 기준 레스토랑 비율로 정해요(킬로그램이 불완전하면 낮은 한도). 맞춤 주문은 ‘맞춤 주문’에서 무게를 적어요. 거리가 없거나 너무 먼 레스토랑에 14일 안에 주문이 있으면 할 일이 생겨요. 주문 화면에도 나오고, 배달 출발은 ‘그래도 배송해요’를 체크하고 메모에 이유를 적어야 기록돼요. 주의: 규정은 <b>제품 분류마다</b> 한도를 정하는데, 관리 화면은 지금 모든 케이크를 하나로 합해요. ‘입력한 기준 이하’는 입력한 숫자보다 적다는 뜻일 뿐, ‘규정에 맞다’는 뜻이 아니에요. 케이크가 어느 분류인지 DDPP에 물어보세요.'],
            ['- <b>Documents</b> : les pièces à montrer lors d\'un contrôle (récépissés de la DDPP, CAP, assurance, vérifications de l\'extincteur et de l\'électricité, plan de maîtrise sanitaire, registre RGPD…). Rangez chaque pièce dans le Drive, puis touchez « Modifier » pour coller son lien et, s\'il y en a une, sa date de validité : la gestion prévient 30 jours avant dans « À faire ». Le jour d\'un contrôle, « Ouvrir » montre la pièce. Un lien « proposé, à vérifier » a été retrouvé dans le Drive par Olivier : ouvrez-le, puis touchez « C\'est le bon document », ou « Modifier » pour coller le bon. La gestion ne garde aucune copie ; un document retiré reste dans « Retirés ».',
             '- <b>서류</b>: 점검 때 보여 줄 서류예요 (DDPP 접수증, CAP, 보험, 소화기와 전기 점검, 위생 관리 계획, 개인정보 처리 대장…). 서류를 Drive에 넣고 ‘수정’을 눌러 링크와, 있으면 유효 기간을 붙여 넣으세요. 기간 30일 전에 ‘할 일’에서 알려 줘요. 점검 날에는 ‘열기’로 서류를 보여 줘요. ‘제안된 링크, 확인 필요’는 올리비에가 Drive에서 찾은 링크예요. 열어 보고 ‘맞는 서류예요’를 누르거나, ‘수정’으로 맞는 링크를 붙여 넣으세요. 관리 화면에는 사본이 없고, 뺀 서류는 ‘뺀 서류’에 남아요.'],
        ]],
        ['id' => 'hygiene', 'titre' => ['Hygiène', '위생'], 'textes' => [
            ['Plus › <b>Hygiène</b> sert à noter, depuis le téléphone, ce qui prouve que les gâteaux sont faits et gardés comme il faut : la température des frigos, les matières premières reçues, les fournées et les livraisons aux restaurants. Aujourd\'hui (accueil) montre où en sont les relevés de froid du jour.',
             '더보기 › <b>위생</b>에서는 케이크를 제대로 만들고 보관했다는 것을 증명하는 기록을 휴대폰으로 남겨요: 냉장고 온도, 받은 재료, 생산, 레스토랑 배달. 오늘(첫 화면)에서 오늘 온도 기록이 어디까지 됐는지 보여요.'],
            ['- <b>Froid</b> : chaque jour de production, tapez la température lue sur chaque frigo et touchez « Noter ». Si elle sort de la plage, la gestion demande ce que vous avez fait (porte refermée, produits jetés, réglage…) : c\'est obligatoire. Les plages préremplies (frigo : 0 à 3 °C ; congélateur : -18 °C ou moins) sont celles de la réglementation : vérifiez-les dans <b>Appareils</b> et touchez « Confirmer cette plage ».',
             '- <b>냉장·냉동</b>: 생산하는 날마다 냉장고마다 읽은 온도를 적고 ‘기록’을 눌러요. 범위를 벗어나면 어떻게 했는지(문 닫음, 제품 버림, 온도 조절…)를 꼭 적어야 해요. 미리 넣어 둔 범위(냉장고: 0~3 °C, 냉동고: -18 °C 이하)는 규정 기준이에요. <b>기기</b>에서 확인하고 ‘이 범위 확인’을 누르세요.'],
            ['- <b>Réceptions</b> : pour la crème, les œufs, le beurre, les fruits… le produit, le lot et la date limite, ou simplement une photo de l\'étiquette. La photo reste dans la gestion, jamais sur le site.',
             '- <b>입고</b>: 크림, 달걀, 버터, 과일 등은 제품, 로트, 유통기한을 적거나 라벨 사진만 찍어요. 사진은 관리 화면에만 있고 사이트에는 절대 나오지 않아요.'],
            ['- <b>Fournées</b> : choisissez le gâteau ; la gestion donne le numéro de lot (date et gâteau, par exemple 261108-TARTEPECAN-1) et la date limite, calculée avec la durée de conservation que vous avez fixée (Appareils › Durées de conservation). Sans durée, tapez la date ou le nombre de jours : la gestion ne la devine jamais. Cochez les réceptions utilisées : c\'est ce qui permet de remonter jusqu\'à elles en cas de problème. Si rien n\'est à cocher (produits du placard, réception oubliée), cochez « Aucune réception notée » et dites pourquoi dans la note : le lot sera marqué « traçabilité incomplète ». Deux fournées notées en même temps ne reçoivent jamais le même numéro.',
             '- <b>생산</b>: 케이크를 고르면 로트 번호(날짜와 케이크, 예: 261108-TARTEPECAN-1)와, 정해 둔 보관 기간(기기 › 케이크 보관 기간)으로 계산한 유통기한이 붙어요. 기간이 없으면 날짜나 일수를 적어요. 관리 화면은 절대 짐작하지 않아요. 사용한 입고를 체크하세요. 문제가 생겼을 때 거슬러 올라갈 수 있어요. 체크할 것이 없으면(찬장 재료, 잊은 입고) ‘기록한 입고 없음’을 체크하고 메모에 이유를 적어요. 그 로트는 ‘추적 정보 불완전’으로 표시돼요. 동시에 기록한 두 생산이 같은 번호를 받는 일은 없어요.'],
            ['- <b>Livraisons aux restaurants</b> : au moment de partir, « Départ d\'une livraison » : la commande ou le restaurant, les lots livrés, la température au départ. Sur place, touchez « Arrivée ». La fiche d\'un lot montre ensuite les réceptions utilisées et les restaurants livrés. Si vous ne savez plus quels lots sont partis, cochez « Lots livrés non notés » et dites pourquoi dans la note : en cas de problème, cette livraison apparaît dans « Livraisons à vérifier ».',
             '- <b>레스토랑 배달</b>: 출발할 때 ‘배달 출발’에서 주문이나 레스토랑, 배달하는 로트, 출발 온도를 적어요. 도착하면 ‘도착’을 눌러요. 로트 기록에서 사용한 입고와 배달한 레스토랑을 볼 수 있어요. 배달한 로트를 모르면 ‘배송한 로트를 기록하지 않음’을 체크하고 메모에 이유를 적어요. 문제가 생기면 그 배송은 ‘확인할 배송’으로 나와요.'],
            ['- Un jour sans production : « Pas de production aujourd\'hui ». Un jour de production (une fournée, une livraison, ou une commande retirée ou livrée ce jour-là) sans relevé de froid fait apparaître le lendemain la démarche « Relevé de froid manquant » dans Obligations : notez ce qui s\'est passé et touchez « C\'est fait ». Un relevé ne se note jamais après coup.',
             '- 생산이 없는 날은 ‘오늘은 생산 없음’을 눌러요. 생산한 날(생산, 배달, 또는 그날 찾아가거나 배달한 주문)에 온도 기록이 없으면 다음 날 의무에 ‘빠진 온도 기록’이 생겨요. 무슨 일이 있었는지 적고 ‘완료’를 누르세요. 온도 기록은 절대 나중에 적지 않아요.'],
            ['- Rien ne s\'efface : un relevé faux se corrige depuis sa fiche (« Corriger ce relevé », avec la raison). L\'original reste visible, avec l\'heure et la personne. <b>Historique</b> montre les 30 derniers jours et télécharge les relevés d\'une période (CSV) pour un contrôle. Les relevés et les photos sont gardés 5 ans (choix d\'Olivier), puis effacés la nuit.',
             '- 아무것도 지워지지 않아요. 잘못된 기록은 그 기록 화면의 ‘이 기록 고치기’에서 이유와 함께 고쳐요. 원래 기록은 시간과 사람과 함께 그대로 보여요. <b>기록</b>에는 최근 30일이 나오고, 점검용으로 기간별 기록을 CSV로 내려받을 수 있어요. 기록과 사진은 5년 동안 보관하고(올리비에의 선택) 그 뒤 밤에 지워져요.'],
            ['- <b>Étiquettes des boîtes</b> : sur la fiche d\'une fournée, choisissez le format (planche A4 de 24 étiquettes 63,5 x 33,9 mm, ou rouleau 55 x 30 mm) et le nombre, puis « Préparer les étiquettes » et imprimez. L\'étiquette porte le nom du gâteau, les ingrédients (allergènes en gras), les allergènes, « À consommer jusqu\'au », le lot, la conservation et l\'adresse de l\'exploitant ; elle reste en français. Avant la première étiquette d\'un gâteau : sa fiche validée par Youngmi (Produits), puis dans Hygiène › Appareils › <b>Étiquettes des gâteaux</b>, relire les ingrédients proposés depuis la recette et la conservation, et toucher « J\'ai relu ce texte » ; l\'adresse de l\'exploitant se tape une fois dans les Réglages de la même page. Choisissez aussi la mention de la date (« À consommer jusqu\'au » pour un gâteau frais ou à la crème, « À consommer de préférence avant le » pour un gâteau sec) et, si le gâteau est vendu emballé à poids fixe, son poids net. La liste d\'ingrédients est obligatoire. S\'il manque quelque chose, la fiche du lot dit quoi : la gestion n\'invente jamais un texte d\'étiquette. Si le texte déborde d\'une étiquette, l\'impression est bloquée : raccourcissez la liste ou choisissez la planche A4.',
             '- <b>상자 라벨</b>: 생산 기록 화면에서 형식(A4 한 장 24개 63.5 x 33.9 mm, 또는 롤 55 x 30 mm)과 개수를 고르고 ‘라벨 준비’를 누른 뒤 인쇄해요. 라벨에는 케이크 이름, 재료(알레르기 유발 성분은 굵게), 알레르기 정보, 소비기한(‘À consommer jusqu\'au’), 로트, 보관 방법, 영업자 주소가 들어가고, 프랑스어로 나와요. 케이크의 첫 라벨 전에: 제품에서 영미가 정보를 확인하고, 위생 › 기기 › <b>케이크 라벨</b>에서 레시피로 제안된 재료와 보관 방법을 읽고 ‘이 글을 확인했어요’를 눌러요. 영업자 주소는 같은 화면의 설정에 한 번만 적어요. 빠진 것이 있으면 로트 화면에 무엇이 빠졌는지 나와요. 날짜 문구(생과자·크림 케이크는 ‘À consommer jusqu\'au’, 마른 과자는 ‘À consommer de préférence avant le’)와, 정해진 무게로 포장해 팔면 순중량도 골라요. 재료 목록은 필수예요. 관리 화면은 라벨 문구를 절대 지어내지 않아요. 글이 라벨을 넘치면 인쇄가 막혀요. 목록을 줄이거나 A4 시트를 고르세요.'],
            ['- <b>Problème sur ce lot</b> (goût anormal, client malade, frigo en panne…) : sur la fiche du lot, décrivez le problème et touchez « Ouvrir le suivi du problème ». La gestion montre qui a reçu le lot (restaurants et clients), les matières premières utilisées, et les étapes : retirer le lot, prévenir les clients, informer la DDPP, déclarer sur RappelConso si le lot a été vendu. Chaque étape se marque « C\'est fait », ou « Sans objet » avec sa raison (jamais pour « Retirer le lot ») ; RappelConso est obligatoire dès qu\'un rappel auprès des consommateurs est décidé, y compris quand un restaurant a pu servir le gâteau à ses clients : demandez à chaque restaurant s\'il l\'a servi ou s\'il l\'a encore. Tant que le problème est ouvert, le lot ne se coche plus dans une livraison et ses étiquettes ne s\'impriment plus. Le brouillon pour la DDPP se copie et s\'envoie par vous, depuis contact@ : aucune IA ne l\'envoie. Une démarche importante apparaît dans Obligations ; elle se clôt avec le problème, quand toutes les étapes sont réglées, avec un bilan et le lien de la preuve rangée dans le Drive. Les relevés d\'un problème ne sont jamais effacés tant qu\'il est ouvert, puis pendant la durée de conservation.',
             '- <b>이 로트에 문제</b>(이상한 맛, 아픈 손님, 냉장고 고장…): 로트 화면에서 문제를 적고 ‘문제 처리 시작’을 눌러요. 로트를 받은 곳(레스토랑과 고객), 사용한 재료, 단계가 나와요: 로트 회수, 고객에게 알리기, DDPP에 알리기, 판매됐으면 RappelConso에 신고. 단계마다 ‘완료’로, 또는 이유와 함께 ‘해당 없음’으로 표시해요(‘로트 회수’는 해당 없음 불가). 소비자 대상 회수를 정하면 RappelConso 신고는 의무예요. 레스토랑이 손님에게 케이크를 냈을 수 있을 때도 마찬가지예요. 레스토랑마다 냈는지, 아직 가지고 있는지 물어보세요. 문제가 열려 있는 동안 그 로트는 배달에서 체크할 수 없고 라벨도 인쇄되지 않아요. DDPP용 초안은 복사해서 contact@에서 직접 보내요. AI는 절대 보내지 않아요. 의무에 중요한 할 일이 생기고, 모든 단계를 정리한 뒤 정리 내용과 Drive 증빙 링크로 문제를 닫으면 함께 닫혀요. 문제의 기록은 열려 있는 동안, 그리고 보관 기간 동안 절대 지워지지 않아요.'],
        ]],
        ['id' => 'statistiques', 'titre' => ['Statistiques', '통계'], 'textes' => [
            ['- <b>Ventes</b> : en haut, le choix <b>Montants HT | TTC</b> (HT au départ, gardé sur l\'appareil) : tous les montants qu\'on compare suivent ce choix, jamais mélangés, ici comme dans les ventes d\'un gâteau et les tuiles d\'Aujourd\'hui. Puis le chiffre d\'affaires total, celui des <b>particuliers</b> et celui des <b>professionnels</b> ; le panier moyen des particuliers et la livraison moyenne des professionnels ; les demandes abouties des particuliers, la réponse aux demandes du site en moins de 24 h, les clients et les fidèles, les avis Google ; le <b>Chiffre d\'affaires par mois</b> : une colonne par mois, les professionnels en bas et les particuliers au-dessus, à la même échelle : la hauteur donne le total du mois (écrit sur chaque mois), les deux couleurs la part de chacun ; un mois touché ouvre ses commandes remises (sur un téléphone, les mois sont en lignes) ; les canaux, où en sont les demandes, et les <b>Ventes par gâteau</b> : pour chaque gâteau, les ventes aux particuliers (B2C) et aux pros (B2B) côte à côte, et depuis quand il est proposé.',
             '- <b>판매</b>: 맨 위에 <b>금액: 부가세 별도 | 부가세 포함</b> 선택이 있어요 (처음엔 부가세 별도, 이 기기에 저장돼요). 비교하는 금액은 모두 이 선택을 따르고 절대 섞이지 않아요. 케이크 판매와 오늘 화면의 칸도 같아요. 그다음 총매출, <b>개인</b> 매출, <b>업체</b> 매출, 개인 평균 주문 금액과 업체 평균 배달 금액, 개인 문의 성사율, 24시간 안에 답장한 사이트 문의, 고객과 단골, 구글 리뷰. <b>월별 매출</b>: 달마다 막대 하나, 아래는 업체, 위는 개인, 같은 비율이에요. 높이는 그달 합계(달마다 적혀 있어요), 두 색은 각자의 몫이에요. 달을 누르면 그달 전달한 주문이 열려요 (휴대폰에서는 달이 줄로 보여요). 그리고 경로, 문의 진행 상황, <b>케이크별 판매</b>: 케이크마다 개인(B2C)과 업체(B2B) 판매를 나란히 보여 주고, 언제부터 판매했는지도 보여 줘요.'],
            ['- <b>Site internet</b> : les visites du site madamecho.com sur 30 jours (Matomo), les pages les plus vues, téléphone ou ordinateur, d\'où viennent les visiteurs, les scans du QR code imprimé sur les emballages et les demandes arrivées par ce QR code.',
             '- <b>웹사이트</b>: 30일 동안의 madamecho.com 방문(Matomo), 가장 많이 본 페이지, 휴대폰과 컴퓨터, 방문자가 온 경로, 포장에 인쇄된 QR 코드 스캔 수와 그 QR 코드로 들어온 문의 수.'],
            ['Les montants sont ceux des commandes remises. Le total additionne les deux groupes ; une moyenne ne les mélange jamais. Chaque chiffre, chaque mois, chaque canal, chaque gâteau et chaque étape se touche : il ouvre la liste des commandes qui le composent, ou la fiche du gâteau.',
             '금액은 전달 완료된 주문 기준이에요. 합계에는 두 그룹을 더하지만, 평균에는 섞지 않아요. 숫자, 월, 경로, 케이크, 단계를 누르면 해당 주문 목록이나 케이크 정보가 열려요.'],
        ]],
        ['id' => 'reglages', 'titre' => ['Paramètres', '설정'], 'textes' => [
            ['<b>Plus › Paramètres</b>, avec l\'engrenage : ce qui se règle une fois.',
             '<b>더보기 › 설정</b>(톱니바퀴 아이콘): 한 번 정해 두는 것들이에요.'],
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
                : ['- <b>Demandes du site</b> : pas encore activé (Olivier l\'active dans Paramètres quand le pilote est fait). En attendant, les demandes du site arrivent seulement par email à contact@madamecho.com : saisissez-les avec « Nouvelle commande ».',
                   '- <b>사이트 문의</b>: 아직 꺼져 있어요(시범 운영이 끝나면 Olivier가 설정에서 켜요). 그동안 사이트 문의는 contact@madamecho.com 이메일로만 와요. ‘새 주문’으로 직접 입력해 주세요.'],
            ['- <b>Accusé de réception</b> (dans « Demandes du site ») : le client qui envoie une demande par le site reçoit tout de suite un mail court qui dit que sa demande est bien arrivée et qu\'on le recontacte très rapidement. C\'est le seul mail qui part sans votre toucher ; il est rangé dans les Échanges de la commande. Un bouton l\'arrête.',
             '- <b>접수 확인 메일</b> (« 사이트 문의» 안): 사이트로 문의한 고객은 바로 짧은 메일을 받아요. 문의를 잘 받았고 곧 연락드린다는 내용이에요. 직접 누르지 않아도 나가는 유일한 메일이고, 주문의 대화 기록에 들어가요. 버튼으로 끌 수 있어요.'],
            ['- <b>Statistiques du site (Matomo)</b> et <b>Assistant</b> : les clés d\'accès, collées une fois par Olivier ; elles ne sont jamais réaffichées.',
             '- <b>사이트 통계 (Matomo)</b>와 <b>어시스턴트</b>: Olivier가 한 번 붙여 넣는 접근 키예요. 다시 보이지 않아요.'],
            ['- <b>Conservation des données (RGPD)</b> : chaque nuit, la gestion efface ce qui a dépassé sa durée (demandes sans suite après 12 mois, clients sans commande depuis 3 ans, journal des connexions après 6 mois).',
             '- <b>데이터 보관 (GDPR)</b>: 매일 밤 보관 기간이 지난 것을 지워요(진행 안 된 문의는 12개월 뒤, 3년 동안 주문이 없는 고객, 6개월 지난 접속 기록).'],
            ['- <b>Miroir pour le Drive</b>, <b>Reprise des données et export</b>, <b>Exporter</b> : la copie pour le Google Sheet du Drive, l\'export complet des données (pour une analyse ou une reprise ; la vraie sauvegarde est celle de l\'hébergement, qu\'Olivier vérifie) et les exports pour un tableur. Après un import, un message dit s\'il a réussi et ce qui a été fait ; <b>Dernières reprises</b> garde les cinq derniers essais (date, qui, réussie, à revoir ou échouée, fichier, résumé). À voir avec Olivier.',
             '- <b>Drive용 사본</b>, <b>데이터 가져오기와 내보내기</b>, <b>내보내기</b>: Drive의 Google Sheet용 사본, 전체 데이터 내보내기(분석이나 가져오기용이에요. 진짜 백업은 호스팅 쪽 백업이고 Olivier가 확인해요), 스프레드시트용 내보내기. 가져오기를 하면 성공했는지, 무엇을 했는지 메시지가 나와요. <b>최근 가져오기</b>에 마지막 다섯 번이 남아요 (날짜, 누가, 성공·확인 필요·실패, 파일, 요약). Olivier와 함께 봐요.'],
            ['- <b>Contrôle technique</b> et <b>Dernières connexions</b> : l\'état de la gestion et qui s\'est connecté.',
             '- <b>기술 점검</b>과 <b>최근 접속</b>: 관리 시스템 상태와 접속한 사람.'],
        ]],
        ['id' => 'assistant', 'titre' => ['L\'assistant', '어시스턴트'], 'textes' => [
            ['L\'assistant (menu Plus) est une <b>IA</b>, reconnaissable à sa petite étoile. Il répond à vos questions sur la gestion et sur vos données, prépare les modifications que vous lui demandez, vous aide quand quelque chose ne marche pas et discute vos idées d\'amélioration. Vous pouvez lui écrire, ou lui parler, en coréen ou en français.',
             '어시스턴트(더보기 메뉴)는 <b>AI</b>예요. 작은 별 모양으로 알아볼 수 있어요. 관리 시스템과 데이터에 대한 질문에 답하고, 부탁한 수정을 준비하고, 뭔가 안 될 때 도와주고, 개선 아이디어를 같이 이야기해요. 한국어나 프랑스어로 쓰거나 말해도 돼요.'],
            ['- Il connaît ce manuel, les règles détaillées du site et de la gestion (le pourquoi de chaque écran), et les données de la gestion : commandes, clients (noms et coordonnées compris), gâteaux, recettes et ingrédients, agenda et indispos, chiffres de vente et visites du site. Demandez-lui par exemple « Qu\'est-ce que j\'ai à préparer demain ? », « Quelles commandes ne sont pas encore payées ? » ou « Combien de visites sur le site cette semaine ? ».',
             '- 어시스턴트는 이 설명서, 사이트와 관리 시스템의 자세한 규칙(화면마다 왜 있는지), 그리고 관리 시스템의 데이터를 알아요: 주문, 고객(이름과 연락처 포함), 케이크, 레시피와 재료, 일정과 예약 불가, 매출과 사이트 방문. 예를 들어 「내일 준비할 게 뭐예요?」, 「아직 결제 안 된 주문은 뭐예요?」, 「이번 주 사이트 방문은 몇 번이에요?」라고 물어보세요.'],
            ['- Si vous ne comprenez pas ce qu\'il faut faire, il vous propose de demander à Olivier, de vive voix ou par « Écrire à Olivier », avec la question à lui poser.',
             '- 무엇을 해야 할지 모르겠으면, 어시스턴트가 올리비에에게 직접 말로 묻거나 ‘Olivier에게 쓰기’로 물어보라고 하고, 물어볼 질문도 알려 줘요.'],
            ['- Il peut <b>préparer une modification</b> : changer l\'étape d\'une commande, noter un paiement, déplacer un retrait, créer une commande ou un client, ajouter une indispo, corriger une recette… Elle s\'affiche sous sa réponse, avec ce qui va changer, et <b>rien ne change tant que vous n\'avez pas touché « Confirmer »</b>. C\'est le formulaire habituel de la gestion qui l\'applique : mêmes contrôles, et c\'est noté dans l\'historique. « Ne pas faire » l\'écarte ; une modification pas confirmée expire après 24 heures. Tout ce qui sera écrit est affiché, le paiement compris : sans paiement dit, celui de la commande ne change pas. Si la fiche a changé depuis, la modification est refusée : redemandez-la.',
             '- 어시스턴트는 <b>수정을 준비</b>할 수 있어요: 주문 단계 바꾸기, 결제 기록, 픽업 날짜 옮기기, 주문이나 고객 만들기, 예약 불가 추가, 레시피 고치기 등. 답 아래에 바뀔 내용이 나오고, <b>‘확인’을 누르기 전에는 아무것도 바뀌지 않아요</b>. 관리 시스템의 평소 양식이 그대로 적용돼요: 같은 점검을 거치고 기록에도 남아요. ‘하지 않기’를 누르면 취소되고, 확인하지 않은 수정은 24시간 뒤 사라져요. 결제를 포함해 기록될 내용이 모두 보여요. 결제를 말하지 않으면 주문의 결제 상태는 그대로예요. 그사이 내용이 바뀌었으면 수정은 거절돼요. 다시 부탁하세요.'],
            ['- Il ne touche jamais aux paramètres, aux comptes, aux mots de passe et aux clés, à l\'import et à l\'export, à la fusion de deux fiches ni à l\'effacement d\'un client, et il n\'envoie aucun email.',
             '- 설정, 계정, 비밀번호와 키, 가져오기와 내보내기, 고객 정보 합치기와 삭제는 절대 건드리지 않고, 이메일도 보내지 않아요.'],
            ['- <b>Les échanges avec les clients</b> : demandez « fais le point sur les échanges » : il dit qui attend une réponse de vous, les propositions sans réponse, et vous aide à écrire la réponse. Racontez-lui un appel ou un message WhatsApp (« Mme Dubois m\'a appelée, elle passe à 16 h ») : il prépare la note, en bon français, avec la façon dont l\'échange a eu lieu ; elle n\'est ajoutée qu\'après votre toucher sur « Confirmer ». Il ne peut pas envoyer de mail.',
             '- <b>고객과의 연락</b>: ‘연락 상황 정리해 줘’라고 하면 누가 답장을 기다리는지, 답이 없는 견적은 무엇인지 알려 주고 답장 쓰기를 도와줘요. 전화나 왓츠앱 내용을 말해 주면(‘뒤부아 씨가 전화했어, 16시에 온대’) 바른 프랑스어로 경로와 함께 메모를 준비해요. ‘확인’을 눌러야만 추가돼요. 이메일은 보낼 수 없어요.'],
            ['- <b>Mode vocal</b>, les mains prises : touchez le <b>micro</b> à côté de la flèche, puis parlez. Il répond à voix haute, puis vous écoute à nouveau. Dites « stop » (ou touchez <b>Arrêter</b>) pour finir. Il parle la langue de votre écran, coréen ou français. Une modification se confirme toujours d\'un toucher sur « Confirmer ». La première fois, le navigateur demande l\'autorisation d\'utiliser le micro. Votre voix est transcrite par le navigateur : Google sur Chrome, Apple sur Safari.',
             '- 손이 바쁠 때는 <b>음성 모드</b>: 화살표 옆의 <b>마이크</b>를 누르고 말하세요. 어시스턴트가 소리 내어 답하고 다시 들어요. 끝내려면 “그만”이라고 말하거나 <b>멈추기</b>를 누르세요. 화면 언어(한국어나 프랑스어)로 말해요. 수정은 언제나 ‘확인’을 눌러서 확정해요. 처음에는 브라우저가 마이크 사용 허락을 물어요. 목소리는 브라우저가 글로 바꿔요: Chrome은 Google, Safari는 Apple.'],
            ['- Il vous dit franchement quand il pense que vous vous trompez, et pourquoi.',
             '- 내가 틀렸다고 생각하면 솔직하게, 이유와 함께 말해 줘요.'],
            ['- Quand un vrai problème ou un vrai besoin apparaît, il prépare un <b>rapport</b>. Un bouton l\'envoie à contact@madamecho.com ; Olivier le lit et regarde comment améliorer la gestion. Rien ne part sans votre bouton.',
             '- 진짜 문제나 진짜 필요한 개선이 보이면 <b>보고서</b>를 준비해요. 버튼을 누르면 contact@madamecho.com으로 보내져요. Olivier가 읽고 관리 시스템을 어떻게 고칠지 봐요. 버튼을 누르기 전에는 아무것도 나가지 않아요.'],
            ['- Écrivez en bas, dans la zone arrondie, puis touchez la flèche (sur ordinateur : Entrée ; Maj + Entrée pour aller à la ligne). Pendant qu\'il réfléchit, son étoile bouge ; la réponse s\'affiche ensuite sous votre question. <b>Nouvelle conversation</b>, en haut, repart de zéro. Si vous remontez pour relire, un rond avec une flèche vers le bas apparaît au-dessus de la zone de saisie : touchez-le pour revenir aux derniers messages.',
             '- 아래 둥근 칸에 쓰고 화살표를 눌러요(컴퓨터: Enter, 줄 바꿈은 Shift + Enter). 생각하는 동안 별이 움직이고, 답은 질문 아래에 나와요. 위의 <b>새 대화</b>는 처음부터 다시 시작해요. 위로 올려 다시 읽을 때는 입력 칸 위에 아래쪽 화살표 동그라미가 나타나요. 누르면 마지막 메시지로 돌아와요.'],
            ['- Ce que vous lui écrivez et les données qu\'il lit pour répondre passent par l\'entreprise qui fait fonctionner l\'assistant (Anthropic, aux États-Unis), pour produire chaque réponse. N\'écrivez jamais d\'information de santé : pour une allergie, seulement l\'ingrédient à éviter.',
             '- 내가 쓴 내용과 어시스턴트가 답을 위해 읽은 데이터는 답을 만들기 위해 어시스턴트를 운영하는 회사(미국의 Anthropic)를 거쳐요. 건강 정보는 절대 적지 마세요. 알레르기는 피할 재료만 적어요.'],
            ['- Vos messages, ce qu\'il a lu et les modifications préparées restent 6 mois dans la gestion, puis sont effacés ; un rapport reste 12 mois. Quand un client est effacé, ce qui le concerne est aussi retiré des conversations. Olivier ne lit que ce que vous lui envoyez. Pour voir ou effacer vos messages : contact@madamecho.com (registre des traitements, ligne T8).',
             '- 메시지, 어시스턴트가 읽은 내용, 준비한 수정은 관리 시스템에 6개월 보관한 뒤 지워져요. 보고서는 12개월 보관해요. 고객 정보를 지우면 대화에서도 그 고객 내용이 지워져요. Olivier는 보내 주신 것만 읽어요. 내 메시지 보기·삭제: contact@madamecho.com (처리 목록 T8).'],
            ['- <b>Écrire à Olivier</b>, en haut de l\'écran de l\'assistant : votre message part tel quel à contact@madamecho.com, sans l\'assistant, en français ou en coréen. Les rapports déjà envoyés y sont listés.',
             '- 어시스턴트 화면 위쪽의 <b>Olivier에게 쓰기</b>: 어시스턴트를 거치지 않고 메시지가 그대로 contact@madamecho.com으로 가요. 한국어로 써도 돼요. 이미 보낸 보고서도 거기에 있어요.'],
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
            ['- Les <b>mots de passe</b> et les <b>clés</b> ne se partagent avec personne, pas même avec l\'assistant. Déconnectez-vous sur un appareil qui n\'est pas le vôtre : le rond avec votre initiale, en haut à droite, puis « Se déconnecter ».',
             '- <b>비밀번호</b>와 <b>키</b>는 누구와도, 어시스턴트와도 공유하지 않아요. 내 기기가 아니면 오른쪽 위 이니셜 동그라미를 누르고 ‘로그아웃’을 눌러요.'],
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
    $c = titre_page('Manuel', fr_ko('Mode d\'emploi, écran par écran', '화면별 사용법'), '', u('plus'))
       . '<p class="aide">' . fr_ko('En cas de doute, ce manuel fait foi. Une question qui n\'y trouve pas sa réponse ? L\'<a href="' . h(u('assistant')) . '">assistant</a> est là pour ça.',
                                   '헷갈릴 때는 이 설명서가 기준이에요. 여기에 답이 없는 질문은 <a href="' . h(u('assistant')) . '">어시스턴트</a>에게 물어보세요.') . '</p>'
       . '<nav class="sommaire" id="sommaire" aria-label="Sommaire">' . $som . '</nav>' . $corps;
    mise_en_page('Manuel', $c, 'manuel');
}
