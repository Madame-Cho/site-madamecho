<?php
/*
 * Madame Cho : espace de gestion. L'écran en coréen pour Youngmi (demande d'Olivier, 06/11/2026).
 * On traduit la page finie, texte par texte : titres, boutons, aides, étapes, messages, dates.
 * Jamais traduits : noms, adresses, gâteaux et leurs tailles, ingrédients, textes des clients, commentaires,
 * emails préparés (ils partent en français), et tout ce qui porte translate="no" (ou est dans textarea, pre, code).
 * Un texte absent du dictionnaire reste en français : rien ne casse. Style : noms courts pour les boutons, 해요체 pour les phrases.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

const KO_MOIS = ['janvier' => 1, 'janv.' => 1, 'février' => 2, 'févr.' => 2, 'mars' => 3, 'avril' => 4, 'avr.' => 4, 'mai' => 5, 'juin' => 6,
                 'juillet' => 7, 'juil.' => 7, 'août' => 8, 'septembre' => 9, 'sept.' => 9, 'octobre' => 10, 'oct.' => 10,
                 'novembre' => 11, 'nov.' => 11, 'décembre' => 12, 'déc.' => 12];
const KO_JOURS = ['dimanche' => '일요일', 'lundi' => '월요일', 'mardi' => '화요일', 'mercredi' => '수요일', 'jeudi' => '목요일', 'vendredi' => '금요일', 'samedi' => '토요일'];
const KO_JOURS_COURTS = ['dim.' => '일', 'lun.' => '월', 'mar.' => '화', 'mer.' => '수', 'jeu.' => '목', 'ven.' => '금', 'sam.' => '토'];
const KO_MOMENTS = ['matin' => '오전', 'midi' => '점심', 'après-midi' => '오후', 'soir' => '저녁'];
/** Les champs nommés dans l'historique (« Fiche modifiée : nom, ville »). */
const KO_CHAMPS = ['type' => '종류', 'prenom' => '이름', 'nom' => '성', 'societe' => '회사', 'contact' => '담당자', 'email' => '이메일', 'telephone' => '전화',
                   'ville' => '도시', 'canal' => '경로', 'qonto' => 'Qonto', 'lettre_info' => '뉴스레터', 'retrait_date' => '픽업 날짜', 'retrait_moment' => '시간대',
                   'retrait_heure' => '시간', 'mode' => '방식', 'perso' => '맞춤 내용', 'besoin' => '업체 요청', 'lignes' => '케이크', 'prix_final' => '최종 가격',
                   'acompte' => '선금', 'facture' => '청구서', 'moyen' => '결제 수단', 'statut' => '상태', 'fiche_lien' => '시트 링크', 'valide_le' => '확인 날짜',
                   'valide_par' => '확인한 사람', 'emballage' => '포장비', 'tva' => '부가세', 'notes' => '메모', 'recette' => '만드는 법'];

/* ---------- Dictionnaire : un texte français exact | sa traduction ---------- */
function coreen_dico(): array {
    static $d = null;
    if ($d !== null) return $d;
    $d = [];
    foreach (explode("\n", coreen_textes()) as $l) {
        $l = trim($l);
        if ($l === '' || $l[0] === '#' || !str_contains($l, ' | ')) continue;
        [$fr, $ko] = explode(' | ', $l, 2);
        // un bouton cité dans une phrase coréenne : ‘새 주문’ ; un mot français cité garde ses guillemets (« lait »)
        $d[coreen_cle($fr)] = preg_replace('/« ([^»]*\p{Hangul}[^»]*) »/u', '‘$1’', trim($ko)) ?? trim($ko);
    }
    return $d;
}
function coreen_cle(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', str_replace(["\u{00A0}", "\u{202F}"], ' ', $s)) ?? $s);
}

function coreen_textes(): string {
    return <<<'TXT'
# Barre, menus, connexion
Aller au contenu | 본문으로 건너뛰기
Essai en local : aucun email ne part, les données sont fictives. | 로컬 테스트: 이메일은 나가지 않고, 데이터는 가상이에요.
Gestion | 관리
Gestion Madame Cho | Madame Cho 관리
Gestion de Madame Cho | Madame Cho 관리
Aujourd'hui | 오늘
Commandes | 주문
Clients | 고객
Produits | 제품
Statistiques | 통계
Production | 생산
Réglages | 설정
Plus | 더보기
Menu | 메뉴
Menu principal | 주 메뉴
Nouvelle commande | 새 주문
Nouvelle | 새 주문
Nouveau | 새 고객
Retour | 뒤로
Connexion | 로그인
Clients, commandes et produits. Accès réservé. | 고객, 주문, 제품 관리. 허락된 사람만 들어올 수 있어요.
Email | 이메일
Mot de passe | 비밀번호
Rester connectée sur cet appareil (30 jours) | 이 기기에서 로그인 유지 (30일)
Se connecter | 로그인
Première connexion ou mot de passe oublié ? | 처음 접속하거나 비밀번호를 잊으셨나요?
Première connexion : choisir mon mot de passe | 처음 접속: 비밀번호 정하기
Première connexion : choisissez d'abord votre mot de passe avec le lien ci-dessous, envoyé à contact@madamecho.com. | 처음 접속이에요. 먼저 아래 링크로 비밀번호를 정하세요. 링크는 contact@madamecho.com으로 보내져요.
Trop d'essais en peu de temps : attendez un quart d'heure, ou choisissez un nouveau mot de passe. | 짧은 시간에 시도가 너무 많았어요. 15분 기다리거나 새 비밀번호를 정하세요.
Email ou mot de passe incorrect. | 이메일 또는 비밀번호가 맞지 않아요.
Choisir mon mot de passe | 비밀번호 정하기
Si cette adresse a un accès, un email vient de partir avec un lien valable une heure. Regardez aussi dans les indésirables. | 이 주소에 접근 권한이 있으면, 1시간 동안 쓸 수 있는 링크를 이메일로 방금 보냈어요. 스팸함도 확인해 보세요.
Retour à la connexion | 로그인으로 돌아가기
Indiquez l'adresse de votre accès : vous recevrez un lien pour choisir votre mot de passe. Pour Madame Cho, c'est contact@madamecho.com. | 접속에 쓰는 이메일 주소를 입력하세요. 비밀번호를 정하는 링크를 보내 드려요. Madame Cho는 contact@madamecho.com이에요.
Recevoir le lien | 링크 받기
Lien expiré | 만료된 링크
Ce lien ne marche plus | 이 링크는 더 이상 쓸 수 없어요
Il a expiré ou a déjà servi. Demandez-en un nouveau : il arrive en une minute. | 기한이 지났거나 이미 사용한 링크예요. 새 링크를 요청하세요. 1분 안에 와요.
Recevoir un nouveau lien | 새 링크 받기
Nouveau mot de passe | 새 비밀번호
Le même, une seconde fois | 새 비밀번호 한 번 더
Enregistrer et entrer | 저장하고 들어가기
Les deux mots de passe ne sont pas identiques. | 두 비밀번호가 같지 않아요.
Le mot de passe est trop long. | 비밀번호가 너무 길어요.
Ce mot de passe est trop facile à deviner. | 이 비밀번호는 너무 쉽게 짐작할 수 있어요.
Votre mot de passe est enregistré. Bienvenue dans la gestion de Madame Cho. | 비밀번호를 저장했어요. Madame Cho 관리에 오신 걸 환영해요.
Afficher le mot de passe | 비밀번호 보기
Afficher les mots de passe | 비밀번호 보기

# Erreurs
Erreur | 오류
Un souci technique | 기술적인 문제
L'action n'a pas pu se faire. Rien n'est perdu : revenez en arrière et réessayez. Si cela recommence, prévenez Olivier en lui disant ce que vous faisiez. | 작업을 끝내지 못했어요. 잃어버린 건 없어요. 뒤로 가서 다시 해 보세요. 또 이러면 무엇을 하던 중이었는지 Olivier에게 알려 주세요.
Revenir à l'accueil | 처음 화면으로
Introuvable | 찾을 수 없음
Commande introuvable | 주문을 찾을 수 없어요
Cette commande n'existe pas. | 이 주문은 없어요.
Si elle vient d'un email du site, l'enregistrement a pu échouer : créez la commande à partir de l'email. | 사이트 이메일로 온 문의라면 저장이 안 됐을 수 있어요. 이메일을 보고 주문을 만드세요.
Les demandes du site ne s'enregistrent pas encore toutes seules : créez la commande à partir de l'email. | 사이트 문의는 아직 자동으로 저장되지 않아요. 이메일을 보고 주문을 만드세요.
Créer la commande | 주문 만들기

# Aujourd'hui
À répondre | 답장 필요
Retraits à venir | 다가오는 픽업
Panier moyen | 평균 주문 금액
Fiche de production | 생산 시트
réponse promise sous 24 h | 24시간 안에 답장 약속
7 prochains jours | 앞으로 7일
Retraits passés à clôturer | 마무리할 지난 픽업
Ces commandes confirmées ont une date de retrait passée : marquez-les remises ou annulées. | 확정된 주문인데 픽업 날짜가 지났어요. 전달 완료 또는 취소로 표시해 주세요.
Problèmes ouverts | 해결 안 된 문제
Demandes à répondre | 답장할 문의
Aucune demande en attente. | 기다리는 문의가 없어요.
Demain | 내일
Retraits des 7 prochains jours | 앞으로 7일 동안의 픽업
Aucun retrait confirmé cette semaine. | 이번 주에 확정된 픽업이 없어요.
Propositions en attente de réponse du client | 고객 답장을 기다리는 견적
Acomptes attendus | 받아야 할 선금
Voir les statistiques | 통계 보기

# Étapes, paiements, canaux, types
Demande reçue | 문의 접수
Proposition envoyée | 견적 발송
Proposition | 견적
Confirmée | 확정
Remise | 전달 완료
Sans suite | 진행 안 됨
Annulée | 취소
Pas encore payé | 미결제
Acompte demandé | 선금 요청
Acompte reçu | 선금 받음
Payé | 결제 완료
payé | 결제 완료
Remboursé | 환불
Moyen non noté | 결제 수단 미기록
Carte (Tap to Pay) | 카드 (Tap to Pay)
Virement | 계좌 이체
Espèces | 현금
Autre | 기타
Site | 사이트
Instagram | 인스타그램
Téléphone | 전화
Marché | 마켓
Sur place | 현장
Restaurant | 레스토랑
Carte | 메뉴
Personnalisée | 맞춤
Personnalisé | 맞춤
Professionnel | 업체
Particulier | 개인
Pro | 업체
Demande pro | 업체 문의
Commande | 주문
Problème | 문제
Le matin, de 9 h à 12 h | 오전 (9시~12시)
À midi, de 12 h à 14 h | 점심 (12시~14시)
L'après-midi, de 14 h à 18 h | 오후 (14시~18시)
Le soir, de 18 h à 19 h | 저녁 (18시~19시)
matin | 오전
midi | 점심
après-midi | 오후
soir | 저녁
Retrait à Meudon | Meudon에서 픽업
Livraison | 배달
Retrait | 픽업
Date de retrait à fixer | 픽업 날짜 미정
Date de livraison à fixer | 배달 날짜 미정
Sans date | 날짜 없음
à convenir | 정할 예정
(sans contenu) | (내용 없음)
Client effacé | 삭제된 고객
À la carte | 메뉴에 있음
Sur demande seulement | 요청 시에만
Retiré | 판매 중단
Actif | 활성
Inactif | 비활성
Effacé | 삭제됨
Effacée | 삭제됨

# Commandes
En cours | 진행 중
À venir | 예정
Terminées | 끝난 주문
Problèmes | 문제
Toutes | 전체
Demandes et commandes, de la demande à la remise | 문의부터 전달까지, 모든 문의와 주문
Chercher | 검색
Nom, référence, facture… | 이름, 주문 번호, 청구서…
Filtrer les commandes | 주문 필터
Aucune commande en cours. | 진행 중인 주문이 없어요.
Aucun retrait confirmé à venir. | 예정된 확정 픽업이 없어요.
Aucun problème ouvert. | 해결 안 된 문제가 없어요.
Aucune commande. | 주문이 없어요.

# Une commande
Prochaine étape : envoyer une proposition | 다음 단계: 견적 보내기
Vérifiez vos disponibilités, puis répondez au client par écrit : prix TTC total, jour et heure du retrait, acompte s'il y a une personnalisation. | 일정을 확인한 뒤 고객에게 글로 답장하세요: 부가세 포함 총액, 픽업 날짜와 시간, 맞춤 케이크라면 선금.
Préparer la proposition | 견적 이메일 준비
Relisez et complétez avant d'envoyer : c'est vous qui envoyez, depuis contact@madamecho.com. | 보내기 전에 다시 읽고 채워 넣으세요. 보내는 건 직접, contact@madamecho.com에서 해요. 고객에게 가는 이메일이라 프랑스어예요.
Objet : | 제목:
Ouvrir dans ma messagerie | 메일 앱에서 열기
Copier le texte | 텍스트 복사
Pas d'email pour ce client : copiez le texte dans un message. | 이 고객은 이메일이 없어요. 텍스트를 복사해서 메시지로 보내세요.
Retrouver la demande dans Gmail | Gmail에서 문의 찾기
Prix proposé (€ TTC) | 제안 가격 (€, 부가세 포함)
Acompte demandé (€) | 요청할 선금 (€)
Vide s'il n'y en a pas. | 없으면 비워 두세요.
J'ai envoyé la proposition | 견적을 보냈어요
Classer sans suite | 진행 안 됨으로 정리
Le client a accepté ? | 고객이 수락했나요?
Jour du retrait | 픽업 날짜
Heure convenue | 약속한 시간
Prix final (€ TTC) | 최종 가격 (€, 부가세 포함)
Moyen de l'acompte | 선금 결제 수단
Confirmer la commande | 주문 확정
Retrait passé : la commande a été remise ? | 픽업 날짜가 지났어요. 주문을 전달했나요?
Envoyer la confirmation au client | 고객에게 확정 이메일 보내기
Ajouter à Google Agenda | 구글 캘린더에 추가
Fichier agenda (.ics) | 캘린더 파일 (.ics)
Payé par | 결제 수단
Paiement | 결제
N° de facture Qonto (si facture) | Qonto 청구서 번호 (청구서가 있으면)
Commande remise au client | 고객에게 전달 완료
L'avis est arrivé | 리뷰가 올라왔어요
À proposer à tous les clients de la même façon, sans trier, sans contrepartie et sans demander une bonne note (règles de Google). | 모든 고객에게 똑같이 부탁하세요. 고르지 말고, 대가 없이, 좋은 별점을 요구하지 않고요 (구글 규칙).
Demander un avis Google | 구글 리뷰 부탁하기
J'ai demandé un avis | 리뷰를 부탁했어요
Rouvrir la demande | 문의 다시 열기
Client | 고객
Nom | 이름
Nom de famille | 성
Prénom | 이름
Ville | 도시
Historique | 기록
Voir la fiche du client | 고객 정보 보기
Quand | 언제
Moment demandé | 요청한 시간대
Où | 장소
Au 4 avenue du Château, 92190 Meudon | 4 avenue du Château, 92190 Meudon
Total indicatif (prix de la carte) | 참고 합계 (메뉴 가격)
Total des lignes | 항목 합계
Total indicatif | 참고 합계
Gâteau personnalisé | 맞춤 케이크
Demande d'un professionnel | 업체 요청
Aucun gâteau noté. | 적힌 케이크가 없어요.
Contenu | 내용
Modifier la commande | 주문 수정
Montant et paiement | 금액과 결제
Prix final | 최종 가격
Acompte | 선금
Reste à payer | 남은 금액
Facture Qonto | Qonto 청구서
Modifier le paiement | 결제 수정
Moyen | 결제 수단
Acompte (€) | 선금 (€)
N° de facture Qonto | Qonto 청구서 번호
Enregistrer | 저장
Problème signalé. | 신고된 문제예요.
Comment il a été résolu | 어떻게 해결했나요
Marquer résolu | 해결됨으로 표시
Commentaires et historique | 메모와 기록
Ajouter un commentaire | 메모 추가
Utile et factuel. Jamais d'information de santé : pour une allergie, notez seulement l'ingrédient à éviter. | 쓸모 있고 사실대로 적어요. 건강 정보는 적지 마세요. 알레르기는 피해야 할 재료만 적어요.
Ajouter | 추가
Rien pour l'instant. | 아직 아무것도 없어요.
Autres actions | 다른 작업
Signaler un problème | 문제 신고
Le problème | 문제 내용
Retard, réclamation, paiement… Une allergie ou un malaise d'un client : prévenez aussi tout de suite Youngmi (R-39), sans noter de détail médical. | 지연, 불만, 결제… 고객의 알레르기나 몸이 안 좋았던 일은 바로 Youngmi에게도 알려 주세요 (R-39). 의료 정보는 적지 마세요.
Signaler | 신고
Annuler la commande | 주문 취소
Annulée par | 취소한 쪽
le client | 고객
Motif (facultatif) | 이유 (선택)
L'acompte | 선금
Je décide plus tard | 나중에 정할게요
Reste acquis (moins de 24 h avant) | 돌려주지 않음 (24시간 이내 취소)
Est remboursé | 환불함
Annuler cette commande ? | 이 주문을 취소할까요?
Changer l'étape à la main | 단계 직접 바꾸기
Étape | 단계
Précision (facultatif) | 설명 (선택)
Changer | 바꾸기
Faire une copie (client régulier) | 복사해서 새 주문 (단골 고객)
Personnalisé : | 맞춤:
Pro : | 업체:

# Nouvelle commande, modifier une commande
Une demande reçue par Instagram, téléphone, email, sur un marché… ou une vente déjà faite | 인스타그램, 전화, 이메일, 마켓 등으로 받은 문의… 또는 이미 끝난 판매
Client déjà connu | 기존 고객
Tapez un nom ou CL-… | 이름이나 CL-… 입력
Ou un nouveau client | 또는 새 고객
Type | 종류
Entreprise ou restaurant | 회사 또는 레스토랑
La demande | 문의
Reçue par | 접수 경로
Référence MC-… (si elle existe déjà, sinon laissez vide) | 주문 번호 MC-… (이미 있을 때만, 없으면 비워 두세요)
Les gâteaux | 케이크
— Choisir un gâteau — | — 케이크 선택 —
Gâteau | 케이크
Quantité | 수량
Plaque en chocolat | 초콜릿 플레이트
Prénom, message (gâteaux entiers) | 이름, 메시지 (홀케이크)
Une ligne de plus | 한 줄 더
Nombre de personnes, saveurs, couleurs, décoration. Pas d'information de santé : pour une allergie, seulement l'ingrédient à éviter. | 인원수, 맛, 색, 장식. 건강 정보는 적지 마세요. 알레르기는 피해야 할 재료만 적어요.
Le retrait | 픽업
Jour | 날짜
Moment | 시간대
Mode | 방식
Montant | 금액
Vide : le prix de la carte. | 비워 두면 메뉴 가격이에요.
Où en est-elle ? | 지금 어느 단계예요?
Demande reçue, à répondre | 문의 접수, 답장 필요
Proposition déjà envoyée | 견적 이미 보냄
Déjà confirmée | 이미 확정
Déjà remise (vente faite) | 이미 전달 (판매 완료)
Commentaire (facultatif) | 메모 (선택)
Enregistrer les changements | 변경 내용 저장
Ajoutez au moins un gâteau, ou décrivez la demande. | 케이크를 하나 이상 넣거나 문의 내용을 적어 주세요.
Choisissez un client existant, ou indiquez au moins le nom du nouveau client. | 기존 고객을 고르거나, 새 고객의 이름이라도 적어 주세요.
L'email du client n'est pas valide. | 고객 이메일 주소가 올바르지 않아요.

# Fiche de production
Commandes confirmées, sans email ni téléphone : à imprimer pour l'atelier | 확정된 주문 (이메일, 전화번호 제외): 작업실용으로 인쇄하세요
Imprimer | 인쇄
À partir du | 시작 날짜
Sur | 기간
Ce jour | 이날만
2 jours | 2일
7 jours | 7일
Afficher | 보기
Aucune commande confirmée sur cette période. | 이 기간에 확정된 주문이 없어요.
À préparer en tout | 준비할 전체 수량

# Clients
Particuliers | 개인
Professionnels | 업체
Tous | 전체
Avec les effacés | 삭제된 고객 포함
Nom, email, téléphone, ville, CL-… | 이름, 이메일, 전화, 도시, CL-…
Filtrer les clients | 고객 필터
Aucun client pour l'instant : ils arrivent avec les demandes du site, ou par « Nouveau ». | 아직 고객이 없어요. 사이트 문의와 함께 들어오거나 « 새 고객 »으로 추가해요.
Avis reçu | 리뷰 받음
Avis demandé | 리뷰 요청함
Pas encore de commande remise | 아직 전달한 주문이 없어요
Commandes remises | 전달 완료 주문
Total | 합계
TTC | 부가세 포함
Dernière commande | 최근 주문
Coordonnées | 연락처
Entreprise | 회사
Contact | 담당자
Premier contact | 첫 연락
Dernier échange | 최근 연락
Dans Qonto | Qonto 등록
Oui | 예
Modifier | 수정
Avis Google | 구글 리뷰
On propose un avis après une commande remise, à tous les clients de la même façon. | 리뷰는 주문을 전달한 뒤 모든 고객에게 똑같이 부탁해요.
Goûts, habitudes, préférences de retrait… Jamais d'information de santé : pour une allergie, seulement l'ingrédient à éviter. | 취향, 습관, 선호하는 픽업 방식… 건강 정보는 적지 마세요. 알레르기는 피해야 할 재료만 적어요.
Fiche et droits du client | 고객 정보와 권리
Un doublon ? Fusionner une autre fiche dans celle-ci | 중복인가요? 다른 고객 정보를 여기에 합치기
Numéro de l'autre fiche (elle sera vidée) | 다른 고객 번호 (그쪽 정보는 비워져요)
Fusionner | 합치기
Fusionner l'autre fiche dans celle-ci ? Ses commandes passent ici. | 다른 고객 정보를 여기에 합칠까요? 그 고객의 주문이 이쪽으로 옮겨져요.
Doublon possible | 중복 가능성
Une autre fiche ressemble à celle-ci. Si c'est la même personne, fusionnez : on garde la fiche la plus ancienne, avec toutes les commandes. Rien n'est fusionné tout seul. | 이 고객과 비슷한 고객 정보가 있어요. 같은 사람이면 합치세요. 더 오래된 고객 정보를 남기고 주문을 모두 옮겨요. 저절로 합쳐지지는 않아요.
Fusionner les deux fiches | 두 고객 정보 합치기
Ce n'est pas la même personne | 같은 사람이 아니에요
Cette autre fiche n'existe plus. | 그 고객 정보는 이제 없어요.
Noté : ces deux fiches ne seront plus signalées comme doublon. | 저장했어요. 이 두 고객 정보는 더 이상 중복으로 표시하지 않아요.
Gamme pro | 업체용 제품
Gamme pro (restaurants) | 업체용 제품 (레스토랑)
Ajouter un produit à la gamme pro | 업체용 제품 추가
Ajouté à la gamme pro. | 업체용 제품에 추가했어요.
Gamme pro enregistrée. | 업체용 제품을 저장했어요.
Indiquez le nom et le prix HT (par exemple 3,50). | 이름과 부가세 별도 가격을 적어 주세요 (예: 3,50).
Pas encore de produit pro : ajoutez-les, ou importez le catalogue de Qonto Facturation. | 아직 업체용 제품이 없어요. 직접 추가하거나 Qonto Facturation의 제품 목록을 가져오세요.
Prix HT, comme sur les factures Qonto. Ils restent dans la gestion et ne vont jamais sur le site. | Qonto 청구서처럼 부가세 별도 가격이에요. 관리 화면에만 있고 사이트에는 절대 나오지 않아요.
Nom (comme dans Qonto) | 이름 (Qonto와 똑같이)
Prix HT (€) | 부가세 별도 가격 (€)
Vendu | 판매 단위
Dans la gamme | 판매 여부
restaurants, prix HT | 레스토랑용, 부가세 별도 가격
Modifier le prix, l'unité ou la TVA | 가격, 판매 단위, 부가세 수정
Le prix doit rester le même que dans Qonto Facturation. Un changement vaut pour les prochaines livraisons ; celles déjà notées gardent leur prix. | 가격은 Qonto Facturation과 같아야 해요. 바꾸면 다음 배달부터 적용되고, 이미 적은 배달은 원래 가격 그대로예요.
Pour une unité vendue. Quantités en grammes, millilitres ou pièces. Le coût utilise le prix de chaque ingrédient. | 판매 단위 1개 기준이에요. 수량은 g, ml 또는 개로 적어요. 원가는 재료별 가격으로 계산해요.
À donner au restaurant avec la livraison : il doit pouvoir les indiquer à ses clients. C'est Youngmi qui tranche un doute (R-39). | 배달할 때 레스토랑에 알려 주세요. 레스토랑은 손님에게 알릴 수 있어야 해요. 애매하면 Youngmi가 정해요 (R-39).
Recette | 레시피
Aucune livraison en 12 mois | 최근 12개월 배달 없음
Nouvelle livraison | 새 배달
Les produits | 제품
— Choisir un produit — | — 제품 선택 —
Autre commande | 다른 주문
La livraison | 배달
Le jour | 날짜
À livrer | 배달 예정
Déjà livrée | 이미 배달함
Enregistrer la livraison | 배달 저장
Livraison enregistrée. | 배달을 저장했어요.
prix HT de la gamme pro, facturés en fin de mois | 업체용 제품의 부가세 별도 가격, 월말 청구
Vide : le total des gâteaux. | 비워 두면 케이크 합계로 해요.
Livraison passée : elle a été faite ? | 배달 날짜가 지났어요. 배달했나요?
Pas de paiement à la livraison : le restaurant paie la facture Qonto du mois, faite à partir du relevé. | 배달할 때는 결제하지 않아요. 레스토랑은 월별 내역서로 만든 Qonto 청구서를 결제해요.
Livrée | 배달 완료
Livraison notée : elle est dans le relevé du mois du restaurant. | 배달을 적었어요. 레스토랑의 월별 내역서에 들어가요.
Pas encore facturée. | 아직 청구 안 함.
Pas encore facturé. | 아직 청구 안 함.
pas encore facturé | 아직 청구 안 함
facturé en partie | 일부 청구함
livraison, facture du mois | 배달, 월말 청구
Total HT | 합계 (부가세 별도)
Total TTC | 합계 (부가세 포함)
Total TTC (TVA comprise) | 합계 (부가세 포함)
Relevés du mois | 월별 내역서
Une livraison = une commande. En fin de mois, le relevé donne les lignes et le total à recopier dans la facture Qonto. | 배달 1건이 주문 1건이에요. 월말에 내역서의 항목과 합계를 Qonto 청구서에 옮겨 적어요.
À facturer | 청구 전
Facturé | 청구함
Livraisons | 배달
Produit | 제품
À recopier dans la facture Qonto | Qonto 청구서에 옮겨 적기
Les prix HT viennent de la gamme pro (Produits). La TVA est calculée sur le total HT, comme dans Qonto : la facture doit arriver au même total TTC, sinon un prix a changé. | 부가세 별도 가격은 업체용 제품(제품 메뉴)에서 가져와요. 부가세는 Qonto처럼 부가세 별도 합계로 계산해요. 청구서의 부가세 포함 합계가 같아야 해요. 다르면 가격이 바뀐 거예요.
Facture et paiement | 청구서와 결제
Numéro de la facture Qonto | Qonto 청구서 번호
Facture payée | 청구서 결제됨
Une fois la facture faite dans Qonto, notez son numéro ici : il s'inscrit sur les livraisons livrées qui n'en ont pas encore, jamais par-dessus un autre numéro. Cochez « Facture payée » quand le virement arrive. | Qonto에서 청구서를 만들면 번호를 여기에 적으세요. 아직 번호가 없는 배달 완료 건에만 적히고, 다른 번호를 덮어쓰지 않아요. 이체가 들어오면 « 청구서 결제됨 »에 체크하세요.
Aucune livraison ce mois-là. | 그달에는 배달이 없어요.
Payée par | 결제 수단
Relevés à facturer | 청구할 내역서
Noter le paiement | 결제 적기
Rien à changer : c'était déjà noté. | 바꿀 게 없어요. 이미 적혀 있어요.
Rien à facturer ce mois-ci : chaque livraison livrée a déjà sa facture. | 이번 달은 청구할 게 없어요. 배달 완료 건마다 이미 청구서가 있어요.
Toutes les livraisons livrées de ce mois ont déjà une facture : pour corriger un numéro, ouvrez la livraison. | 이번 달 배달 완료 건에는 이미 청구서가 다 있어요. 번호를 고치려면 해당 배달을 여세요.
Marge sur les matières et l'emballage seulement : le travail, l'énergie, les pertes et les charges ne sont pas comptés. | 재료와 포장만 계산한 마진이에요. 인건비, 에너지, 손실, 고정비는 포함되지 않아요.
Restaurants livrés le mois dernier ou avant : faites la facture dans Qonto à partir du relevé, puis notez son numéro. | 지난달이나 그 전에 배달한 레스토랑이에요. 내역서를 보고 Qonto에서 청구서를 만든 뒤 번호를 적으세요.
Indiquez le numéro de la facture Qonto, ou cochez « Facture payée ». | Qonto 청구서 번호를 적거나 « 청구서 결제됨 »에 체크하세요.
Le client demande ses données (droit d'accès) | 고객이 자기 정보를 요청했어요 (열람권)
Le fichier contient tout ce que la gestion garde sur lui : à lui envoyer depuis contact@ dans le mois. | 이 파일에는 관리 화면에 있는 이 고객의 정보가 모두 들어 있어요. 한 달 안에 contact@에서 고객에게 보내세요.
Télécharger ses données | 고객 정보 다운로드
Le client demande l'effacement | 고객이 삭제를 요청했어요
Son nom, ses coordonnées, ses commentaires et les textes de ses demandes sont effacés tout de suite. Les montants et les dates restent, sans rien qui le désigne (comptes et statistiques). Les factures Qonto ne sont pas touchées : elles doivent être gardées 10 ans. | 이름, 연락처, 메모, 문의 내용은 바로 삭제돼요. 금액과 날짜는 누구인지 알 수 없게 남아요 (회계와 통계용). Qonto 청구서는 건드리지 않아요. 청구서는 10년 동안 보관해야 해요.
Motif | 이유
Tapez EFFACER pour confirmer | 확인하려면 EFFACER를 입력하세요
Effacer ses données | 고객 정보 삭제
Marquer inactif (ne commande plus) | 비활성으로 표시 (더 이상 주문하지 않음)
Réactiver | 다시 활성화
Nouveau client | 새 고객
Seulement ce qui sert à préparer et remettre les commandes | 주문을 준비하고 전달하는 데 필요한 정보만
Entreprise ou restaurant (professionnels) | 회사 또는 레스토랑 (업체)
Personne à contacter (professionnels) | 담당자 (업체)
Premier contact par | 첫 연락 경로
Nom du client dans Qonto (s'il a une facture) | Qonto의 고객 이름 (청구서가 있으면)
Ni date de naissance, ni adresse si elle ne sert pas, ni information de santé (R-38 et politique de confidentialité). | 생년월일, 쓰지 않을 주소, 건강 정보는 적지 마세요 (R-38, 개인정보 처리방침).
Créer le client | 고객 만들기
Indiquez au moins un nom ou une entreprise. | 이름이나 회사 이름을 적어 주세요.
L'email n'est pas valide. | 이메일 주소가 올바르지 않아요.
Lettre d'information | 뉴스레터

# Produits et ingrédients
La carte du site, avec les recettes, les coûts et les ventes | 사이트 메뉴: 레시피, 원가, 판매
Ingrédients | 재료
Ingrédient | 재료
Recette à saisir | 레시피 입력 필요
Fiche à valider par Youngmi | Youngmi 확인 필요
Aucune vente remise en 12 mois | 최근 12개월 판매 없음
La carte du site n'est pas encore chargée. | 사이트 메뉴를 아직 불러오지 못했어요.
La liste suit la carte du site. Un nouveau gâteau, un prix ou un texte se change dans le site, pas ici. | 이 목록은 사이트 메뉴를 따라요. 새 케이크, 가격, 설명은 여기가 아니라 사이트에서 바꿔요.
Voir sur le site | 사이트에서 보기
Sur le site | 사이트에 보이는 내용
Présentation | 소개
Composition | 구성
Allergènes | 알레르기 유발 성분
Conservation | 보관
À commander | 주문 기한
Ces textes et ces prix viennent du site : pour les changer, demandez la modification du site (ils engagent Madame Cho, R-39). | 이 설명과 가격은 사이트에서 가져와요. 바꾸려면 사이트 수정을 요청하세요 (Madame Cho가 책임지는 내용이에요, R-39).
D'après la recette : | 레시피 기준:
aucun allergène | 알레르기 유발 성분 없음
La recette et le site disent la même chose. | 레시피와 사이트 내용이 같아요.
Recettes, coûts et marges | 레시피, 원가, 마진
Pour un gâteau entier de chaque taille. Quantités en grammes, millilitres ou pièces. Le coût utilise le prix de chaque ingrédient. | 크기별 홀케이크 1개 기준이에요. 수량은 g, ml 또는 개로 적어요. 원가는 재료별 가격으로 계산해요.
Pour une pièce de chaque parfum. Quantités en grammes, millilitres ou pièces. Le coût utilise le prix de chaque ingrédient. | 맛별 1개 기준이에요. 수량은 g, ml 또는 개로 적어요. 원가는 재료별 가격으로 계산해요.
recette vide | 레시피 없음
Coût | 원가
Actions | 작업
prix ? | 가격 ?
Note | 메모
OK | 확인
Retirer | 빼기
Coût matières | 재료비
Prix TTC | 가격 (부가세 포함)
Prix HT | 가격 (부가세 별도)
Marge sur matières | 재료 마진
Coefficient | 배수
prix HT ÷ coût | 부가세 별도 가격 ÷ 원가
— Ingrédient — | — 재료 —
+ Nouvel ingrédient… | + 새 재료…
Nom du nouvel ingrédient | 새 재료 이름
Compté en | 단위
pièce | 개
Prix (€ le kg, le litre ou la pièce) | 가격 (€, kg·리터·개당)
Note (facultatif) | 메모 (선택)
pour la crème, pour le décor… | 크림용, 장식용…
Ajouter à la recette | 레시피에 추가
Copier une recette vers une autre taille ou un autre parfum | 레시피를 다른 크기나 맛으로 복사
Depuis | 원본
Vers (remplace sa recette) | 대상 (대상의 레시피를 덮어써요)
Pour passer d'un diamètre à un autre, multipliez par le rapport des surfaces : de 16 à 20 cm, (20 ÷ 16)² = 1,56. | 지름을 바꿀 때는 면적 비율을 곱하세요. 16 cm에서 20 cm로: (20 ÷ 16)² = 1,56.
Le coefficient multiplie toutes les quantités. | 모든 수량에 배수를 곱해요.
Copier | 복사
Remplacer la recette de destination ? | 대상 레시피를 덮어쓸까요?
Sur la carte | 메뉴 상태
TVA | 부가세
5,5 % (à emporter, consommation différée) | 5,5 % (포장, 나중에 먹는 경우)
10 % (à consommer sur place) | 10 % (매장에서 먹는 경우)
Lien de la fiche technique (dossier 10 du Drive) | 제품 시트 링크 (Drive 10번 폴더)
Fiche validée par Youngmi le | Youngmi 확인 날짜
Validée par | 확인한 사람
Coût de l'emballage (€ par gâteau ou par pièce) | 포장비 (€, 케이크 또는 1개당)
Méthode (étapes, cuissons, montage) | 만드는 법 (단계, 굽기, 조립)
Notes | 메모
Fournisseurs, astuces, ce qui a changé… | 공급업체, 요령, 바뀐 점…
Enregistrer la fiche | 시트 저장
Fiche technique | 제품 시트
Ouvrir la fiche technique | 제품 시트 열기
Ventes des 12 derniers mois | 최근 12개월 판매
Voir les commandes | 주문 보기
Aucune commande remise. | 전달한 주문이 없어요.
Prix | 가격
la pièce | / 개
le kg | / kg
le litre | / L
Cacher les ingrédients retirés | 사용 중단한 재료 숨기기
Voir aussi les ingrédients retirés | 사용 중단한 재료도 보기
Aucun ingrédient : ajoutez le premier ci-dessous, ou directement depuis la recette d'un produit. | 재료가 없어요. 아래에서 첫 재료를 추가하거나, 제품 레시피에서 바로 추가하세요.
Nouvel ingrédient | 새 재료
Grammes pour le sec, millilitres pour les liquides, pièces pour les œufs, gousses, boîtes… | 가루 같은 건 g, 액체는 ml, 달걀·바닐라 깍지·통 같은 건 개
Prix d'achat TTC (€ le kg, le litre ou la pièce) | 구입 가격 (€, 부가세 포함, kg·리터·개당)
Allergènes que contient cet ingrédient | 이 재료에 든 알레르기 유발 성분
Fournisseur | 공급업체
Où et comment l'acheter | 어디서 어떻게 사는지
Marque, référence, étiquette à garder pour les allergènes… | 브랜드, 제품 번호, 알레르기 확인용으로 보관할 라벨…
Prix à indiquer | 가격 입력 필요
Utilisé dans | 사용하는 제품
Dans aucune recette. | 어떤 레시피에도 없어요.
Retiré (n'est plus acheté ; reste dans les anciennes recettes) | 사용 중단 (더 이상 사지 않음, 기존 레시피에는 남아요)
Recette | 레시피

# Statistiques
Depuis le début de la gestion · montants TTC des commandes remises | 관리 시작부터 · 전달한 주문의 부가세 포함 금액
Ventes | 판매
Site internet | 웹사이트
Chiffre d'affaires | 매출
TTC, 24 derniers mois | 부가세 포함, 최근 24개월
Demandes abouties | 성사된 문의
remises ÷ (remises + sans suite + annulées) | 전달 ÷ (전달 + 진행 안 됨 + 취소)
Réponse aux demandes du site | 사이트 문의 답장 시간
moins d'1 h | 1시간 미만
médiane | 중앙값
Clients fidèles | 단골 고객
au moins 2 commandes remises | 전달한 주문 2건 이상
Chiffre d'affaires par mois | 월별 매출
Chiffre d'affaires des 12 derniers mois | 최근 12개월 매출
Le tableau | 표
Mois | 월
Panier | 평균 금액
Pas encore de commande remise. | 아직 전달한 주문이 없어요.
D'où viennent les commandes | 주문이 들어온 경로
Canal | 경로
Demandes | 문의
Remises | 전달
Abouties | 성사율
Gâteaux vendus | 판매한 케이크
Montant TTC | 금액 (부가세 포함)
Les gâteaux personnalisés et les commandes professionnelles sans ligne de la carte ne sont comptés que dans le chiffre d'affaires. | 맞춤 케이크와 메뉴 항목이 없는 업체 주문은 매출에만 들어가요.
Où en sont les demandes | 문의 진행 상황
Exporter | 내보내기
Pour un tableur : sans email ni téléphone. Le fichier contient des noms de clients : à ranger dans le Drive de Madame Cho, pas ailleurs. | 스프레드시트용 파일이에요 (이메일, 전화번호 제외). 고객 이름이 들어 있으니 Madame Cho의 Drive에만 보관하세요.
Commandes (CSV) | 주문 (CSV)
Clients (CSV) | 고객 (CSV)
Le site madamecho.com · 30 derniers jours, d'après Matomo | madamecho.com 사이트 · 최근 30일, Matomo 기준
Actualiser | 새로고침
Ouvrir Matomo | Matomo 열기
Les visites du site | 사이트 방문
Remplacer la clé dans Réglages | 설정에서 키 바꾸기
Réessayez dans quelques minutes. | 몇 분 뒤에 다시 해 보세요.
Visites aujourd'hui | 오늘 방문
depuis minuit | 자정부터
Visites sur 7 jours | 최근 7일 방문
Visites sur 30 jours | 최근 30일 방문
Demandes envoyées | 사이트 문의
30 derniers jours | 최근 30일
Temps moyen | 평균 체류 시간
par visite, 30 jours | 방문당, 30일
Visites d'une seule page | 한 페이지만 본 방문
repartent sans voir d'autre page | 다른 페이지를 보지 않고 나감
Visites par jour | 일별 방문
Visites par jour, 30 derniers jours | 최근 30일 일별 방문
Les dates sous le graphique sont des lundis. | 그래프 아래 날짜는 월요일이에요.
Pas encore de visite sur les 30 derniers jours. | 최근 30일 동안 방문이 없어요.
Pas encore de visite sur cette période. | 이 기간에는 방문이 없어요.
D'où viennent les visiteurs | 방문자가 들어온 경로
Origine | 경로
Visites | 방문
Part | 비율
Moteurs de recherche | 검색 엔진
Réseaux sociaux | 소셜 미디어
Sites web | 다른 웹사이트
Entrées directes | 직접 방문
Campagnes | 캠페인
QR code sur les emballages | 포장 QR 코드
Scans sur 7 jours | 최근 7일 스캔
Scans sur 30 jours | 최근 30일 스캔
Pas encore de scan sur les 30 derniers jours. | 최근 30일 동안 스캔이 없어요.
Chaque scan ouvre madamecho.com/qr, que Matomo compte dans la campagne « qr-code » ; un visiteur qui refuse la mesure n'est pas compté. | 스캔할 때마다 madamecho.com/qr이 열리고, Matomo는 이를 ‘qr-code’ 캠페인으로 집계해요. 측정을 거부한 방문자는 집계되지 않아요.
Assistants IA | AI 어시스턴트
Pages les plus vues | 많이 본 페이지
Page | 페이지
Vues | 조회
Pas encore de page vue. | 아직 본 페이지가 없어요.
Téléphone ou ordinateur | 휴대폰 또는 컴퓨터
Appareil | 기기
Téléphone portable | 휴대폰
Grand téléphone | 큰 휴대폰
Ordinateur | 컴퓨터
Tablette | 태블릿
Télévision | TV
Console | 게임기
Inconnu | 알 수 없음
Matomo ne reconnaît pas cette clé | Matomo가 이 키를 알아보지 못해요
cette clé n'a pas accès au site madamecho.com dans Matomo | 이 키로는 Matomo에서 madamecho.com 사이트를 볼 수 없어요
cette clé ne voit pas le site madamecho.com | 이 키로는 madamecho.com 사이트가 보이지 않아요
Matomo ne répond pas | Matomo가 응답하지 않아요
Matomo ne répond pas, délai dépassé | Matomo가 응답하지 않아요 (시간 초과)
Matomo ne répond pas, connexion impossible | Matomo가 응답하지 않아요 (연결 불가)
réponse illisible de Matomo | Matomo 응답을 읽을 수 없어요
erreur de Matomo | Matomo 오류

# Plus, manuel et assistant
Sommaire | 차례
Statistiques du site | 사이트 통계
Ventes, canaux, délais de réponse, avis | 판매, 경로, 답장 시간, 리뷰
Ce qu'il faut préparer, jour par jour | 날짜별로 준비할 것
Prix d'achat et allergènes des recettes | 재료 구입 가격과 알레르기 유발 성분
Langue, mot de passe, signature des emails, lien d'avis, données | 화면 언어, 비밀번호, 이메일 서명, 리뷰 링크, 데이터
Se déconnecter | 로그아웃
Manuel | 설명서
Comment fonctionne la gestion, écran par écran | 관리 시스템 사용법, 화면별로
Assistant | 어시스턴트
Questions, problèmes, idées d'amélioration | 질문, 문제, 개선 아이디어
Nouvelle conversation | 새 대화
Pas encore relié | 아직 연결되지 않음
Relier l'assistant dans Réglages | 설정에서 어시스턴트 연결
Votre message | 메시지
Envoyer | 보내기
Envoyer le rapport à Olivier | Olivier에게 보고서 보내기
Pas maintenant | 나중에
Non envoyé | 보내지 않음
Remplacé par le rapport suivant | 다음 보고서로 대체됨
Rapports envoyés | 보낸 보고서
Écrire à Olivier | Olivier에게 쓰기
Envoyer à Olivier | Olivier에게 보내기
Message envoyé à contact@madamecho.com. | contact@madamecho.com으로 메시지를 보냈어요.
L'email n'est pas parti : réessayez, ou appelez Olivier. | 이메일이 가지 않았어요. 다시 해 보거나 Olivier에게 전화하세요.
Joindre les 10 derniers messages de la conversation | 대화의 마지막 메시지 10개 함께 보내기
À envoyer | 보낼 수 있어요
Plafond du mois, en dollars (0 = aucun) | 이번 달 한도, 달러 (0 = 없음)
Enregistrer le plafond | 한도 저장
Plafond enregistré : aucun. | 한도를 저장했어요: 없음.
(prix publics d'Anthropic ; une question ratée est comptée aussi) | (Anthropic 공개 가격 기준. 실패한 질문도 포함돼요)
Relier l'assistant | 어시스턴트 연결
Clé d'accès Anthropic | Anthropic 접근 키
Modèle | 모델
Ce mois-ci | 이번 달
Nom : « Gestion Madame Cho ». Copiez la clé affichée (elle commence par sk-ant-) : Anthropic ne la montre qu'une fois. | 이름: « Gestion Madame Cho ». 표시된 키(sk-ant-로 시작)를 복사하세요. Anthropic은 한 번만 보여 줘요.
Collez-la ici. La gestion vérifie qu'elle marche avant de l'enregistrer. | 여기에 붙여 넣으세요. 저장하기 전에 작동하는지 확인해요.
Retirer la clé de l'assistant ? Il ne répondra plus. | 어시스턴트 키를 삭제할까요? 더 이상 답하지 않아요.
L'assistant n'est plus relié. | 어시스턴트 연결을 해제했어요.
L'assistant est relié : il répond dans Plus › Assistant. | 어시스턴트를 연결했어요. 더보기 › 어시스턴트에서 답해요.
Collez la clé telle qu'Anthropic l'affiche : elle commence par sk-ant-, sans espace. | Anthropic이 보여 주는 그대로 키를 붙여 넣으세요. sk-ant-로 시작하고 공백이 없어야 해요.
L'assistant n'est pas encore relié : demandez à Olivier de coller la clé dans Réglages. | 어시스턴트가 아직 연결되지 않았어요. Olivier에게 설정에서 키를 붙여 넣어 달라고 하세요.
L'email n'est pas parti : réessayez, ou prévenez Olivier. | 이메일이 나가지 않았어요. 다시 해 보거나 Olivier에게 알려 주세요.
Rapport envoyé à contact@madamecho.com. | 보고서를 contact@madamecho.com으로 보냈어요.
clé refusée par Anthropic | Anthropic이 키를 거부했어요
cette clé n'a pas le droit d'utiliser ce modèle | 이 키는 이 모델을 쓸 권한이 없어요
modèle introuvable chez Anthropic | Anthropic에 그 모델이 없어요
trop de demandes à la fois, réessayez dans une minute | 요청이 너무 많아요. 1분 뒤에 다시 해 보세요
l'assistant est surchargé, réessayez dans un moment | 어시스턴트가 지금 바빠요. 잠시 뒤에 다시 해 보세요
l'assistant a un souci de son côté, réessayez | 어시스턴트 쪽에 문제가 있어요. 다시 해 보세요
l'assistant met trop de temps à répondre | 어시스턴트 응답이 너무 오래 걸려요
impossible de joindre l'assistant | 어시스턴트에 연결할 수 없어요
l'assistant n'a pas pu répondre à ce message | 어시스턴트가 이 메시지에는 답할 수 없었어요
l'assistant n'est pas relié | 어시스턴트가 연결되어 있지 않아요
cURL manque sur ce serveur | 이 서버에 cURL이 없어요
Clé de l'assistant retirée | 어시스턴트 키 삭제
Assistant relié (clé Anthropic) | 어시스턴트 연결 (Anthropic 키)
Problème | 문제
Amélioration | 개선
Question | 질문

# Réglages
Langue de l'écran | 화면 언어
Le choix est gardé sur cet appareil. Ce qu'on écrit (commentaires, clients, recettes) et les emails préparés restent en français. | 이 선택은 이 기기에 저장돼요. 직접 적는 내용(메모, 고객 정보, 레시피)과 준비된 이메일은 프랑스어 그대로예요.
Mon mot de passe | 내 비밀번호
Mot de passe actuel | 현재 비밀번호
Changer le mot de passe | 비밀번호 바꾸기
Emails préparés et avis Google | 준비된 이메일과 구글 리뷰
Signature des emails préparés | 준비된 이메일의 서명
Lien pour laisser un avis Google | 구글 리뷰 작성 링크
Dans la fiche Google de Madame Cho : « Demander des avis », puis copier le lien. Tant qu'il est vide, la gestion ne propose pas de demander d'avis. | 구글의 Madame Cho 프로필에서 « Demander des avis »(리뷰 요청)를 누르고 링크를 복사해 넣으세요. 비어 있으면 관리 화면에서 리뷰 요청을 제안하지 않아요.
Qui a accès | 접근 권한이 있는 사람
Administratrice | 관리자
Membre | 멤버
désactivé | 비활성
invitation en attente | 초대 대기
jamais connecté | 접속한 적 없음
Désactiver | 비활성화
Donner un accès à quelqu'un | 다른 사람에게 접근 권한 주기
Seulement pour une personne qui travaille pour Madame Cho. Elle reçoit un lien pour choisir son mot de passe. Une IA n'a jamais de compte ici. | Madame Cho를 위해 일하는 사람에게만 주세요. 그 사람은 비밀번호를 정하는 링크를 받아요. AI는 여기에 계정을 가질 수 없어요.
Rôle | 역할
Membre (commandes, clients, produits) | 멤버 (주문, 고객, 제품)
Administratrice (aussi les accès et les données) | 관리자 (접근 권한과 데이터도 관리)
Envoyer l'invitation | 초대 보내기
Demandes du site | 사이트 문의
Arrêter l'enregistrement | 자동 저장 끄기
Activer l'enregistrement des demandes du site | 사이트 문의 자동 저장 켜기
Statistiques du site (Matomo) | 사이트 통계 (Matomo)
Les visites du site viennent de Matomo (stats.madamecho.com). Avec une clé d'accès, la gestion les montre dans Statistiques › Site internet, à côté des ventes. | 사이트 방문 숫자는 Matomo(stats.madamecho.com)에서 와요. 접근 키가 있으면 통계 › 웹사이트에서 판매 옆에 보여 줘요.
État | 상태
relié | 연결됨
pas encore relié | 아직 연결 안 됨
Dernière lecture | 마지막으로 읽은 시각
pas encore | 아직 없음
Remplacer la clé | 키 바꾸기
Relier Matomo | Matomo 연결
Roue dentée « Administration », puis « Personnel » › « Sécurité ». | 톱니바퀴 « Administration »을 누르고 « Personnel » › « Sécurité »로 가세요.
En bas, « Jetons d'authentification » : « Créer un nouveau jeton ». Matomo redemande le mot de passe. | 아래쪽 « Jetons d'authentification »에서 « Créer un nouveau jeton »을 누르세요. Matomo가 비밀번호를 다시 물어봐요.
Description : « Gestion Madame Cho ». Copiez le jeton affiché et collez-le ici. | 설명(Description)에 « Gestion Madame Cho »라고 쓰세요. 화면에 나온 토큰을 복사해서 여기에 붙여 넣으세요.
Clé d'accès de Matomo | Matomo 접근 키
Elle reste dans la base de la gestion, hors du site. Elle n'est jamais réaffichée et ne part pas dans l'export. | 키는 사이트 밖에 있는 관리 데이터베이스에만 보관돼요. 다시 보여 주지 않고, 내보내기에도 들어가지 않아요.
Vérifier et enregistrer | 확인하고 저장
Retirer la clé | 키 삭제
Retirer la clé de Matomo ? Les visites du site ne s'afficheront plus dans la gestion. | Matomo 키를 삭제할까요? 관리 화면에 사이트 방문 숫자가 더 이상 보이지 않아요.
Conservation des données (RGPD) | 데이터 보관 (GDPR)
Chaque nuit, la gestion applique les durées annoncées dans la politique de confidentialité : demandes sans suite effacées 12 mois après le dernier échange, clients sans commande ni échange effacés au bout de 3 ans, journal des connexions gardé 6 mois. | 관리 화면은 매일 밤 개인정보 처리방침에 적힌 기간을 적용해요. 진행 안 된 문의는 마지막 연락 후 12개월 뒤 삭제, 주문도 연락도 없는 고객은 3년 뒤 삭제, 접속 기록은 6개월 보관.
Dernier passage de nuit | 마지막 야간 작업
Dernier effacement | 마지막 삭제
À effacer maintenant | 지금 삭제할 것
aucun | 없음
Demandes du site sans suite depuis plus de 11 mois : supprimez aussi leurs emails dans contact@ (P-02, premier lundi du mois) : | 11개월 넘게 진행 안 된 사이트 문의예요. contact@에서 해당 이메일도 지우세요 (P-02, 매월 첫 월요일):
Lancer l'effacement maintenant | 지금 삭제 실행
Effacer maintenant ce qui a dépassé sa durée de conservation ? | 보관 기간이 지난 것을 지금 삭제할까요?
Miroir pour le Drive | Drive용 사본
Le miroir recopie chaque nuit une version réduite des registres (sans email, téléphone ni commentaires) dans un tableur du Drive (dossier 10), pour Mathieu et les récapitulatifs. | 사본은 매일 밤 기록을 간추려(이메일, 전화, 메모 제외) Drive의 스프레드시트(10번 폴더)에 복사해요. Mathieu와 요약 보고서용이에요.
actif | 켜짐
arrêté | 꺼짐
Dernière copie | 마지막 복사
jamais | 없음
Adresse | 주소
Clé à coller dans le script Google | Google 스크립트에 붙여 넣을 키
(elle ne sera plus jamais affichée) : | (다시는 보이지 않아요):
Créer une nouvelle clé (l'ancienne cesse de marcher) | 새 키 만들기 (이전 키는 더 이상 안 돼요)
Créer la clé du miroir | 사본 키 만들기
Arrêter le miroir | 사본 중지
Reprise des données et export | 데이터 가져오기와 내보내기
Reprise : le fichier JSON préparé à partir de Qonto et des demandes déjà reçues (jamais dans le dépôt du site). Sans risque à relancer : ce qui est déjà là est sauté. | 가져오기: Qonto와 이미 받은 문의로 만든 JSON 파일이에요 (사이트 저장소에는 절대 넣지 않아요). 다시 실행해도 괜찮아요. 이미 있는 것은 건너뛰어요.
Fichier de reprise (.json) | 가져오기 파일 (.json)
Importer | 가져오기
Export complet : clients, commandes, produits, ingrédients et recettes dans un fichier JSON (sans les comptes, la clé de l'assistant ni les conversations), pour une analyse ou une reprise. Ce n'est pas une restauration de l'espace : la sauvegarde, c'est celle de l'hébergement OVH (fichier de la base), qu'Olivier vérifie. Le fichier contient des données de clients : à ranger dans le Drive de Madame Cho (P-24), jamais ailleurs. | 전체 내보내기: 고객, 주문, 제품, 재료, 레시피를 JSON 파일 하나로 받아요(계정, 어시스턴트 키, 대화는 빠져요). 분석이나 가져오기용이에요. 관리 공간을 복원하는 백업이 아니에요. 진짜 백업은 OVH 호스팅 쪽 백업(데이터베이스 파일)이고 Olivier가 확인해요. 고객 정보가 들어 있으니 Madame Cho의 Drive에만 보관하세요 (P-24).
Télécharger l'export complet | 전체 데이터 내보내기 다운로드
Contrôle technique | 기술 점검
Base de données | 데이터베이스
indisponible | 사용할 수 없음
Données rangées | 데이터 위치
hors du dossier du site | 사이트 폴더 밖
dans un dossier fermé du site (repli) | 사이트 안의 잠긴 폴더 (대체 위치)
Connexion chiffrée | 암호화된 연결
essai local | 로컬 테스트
pas de https | https 아님
Emails signés (DKIM) | 이메일 서명 (DKIM)
signature du site présente | 사이트 서명 있음
dkim.php absent | dkim.php 없음
Carte du site | 사이트 메뉴
carte absente | 메뉴 없음
Matomo relié | Matomo 연결됨
Matomo pas encore relié | Matomo 아직 연결 안 됨
Dernières connexions | 최근 접속
Réussie | 성공
oui | 예
non | 아니요
oubli | 비밀번호 찾기
nouveau mot de passe | 새 비밀번호
bloqué | 차단됨
miroir | 사본
Gardées 6 mois. Une connexion que vous ne reconnaissez pas : changez le mot de passe et prévenez Olivier (P-17). | 6개월 동안 보관해요. 모르는 접속이 있으면 비밀번호를 바꾸고 Olivier에게 알려 주세요 (P-17).
Aucune. | 없어요.

# Messages après une action
Avis reçu noté. | 리뷰 받음으로 기록했어요.
Avis reçu noté. Merci ! | 리뷰 받음으로 기록했어요. 감사해요!
Ce compte existe déjà. | 이미 있는 계정이에요.
Ce fichier n'est pas un JSON lisible. | 읽을 수 있는 JSON 파일이 아니에요.
Choisissez deux tailles ou parfums différents et un coefficient. | 서로 다른 크기나 맛 두 가지와 배수를 고르세요.
Choisissez la taille ou le parfum, et une quantité (par exemple 120 ou 0,5). | 크기나 맛, 그리고 수량을 고르세요 (예: 120 또는 0,5).
Choisissez le fichier JSON de reprise (2 Mo au plus). | 가져오기용 JSON 파일을 고르세요 (최대 2 MB).
Choisissez un ingrédient. | 재료를 고르세요.
Client créé. | 고객을 만들었어요.
Collez la clé telle que Matomo l'affiche : une suite de lettres et de chiffres, sans espace. | Matomo에 나온 그대로 키를 붙여 넣으세요. 공백 없이 글자와 숫자로 된 값이에요.
Commande annulée. | 주문을 취소했어요.
Commande confirmée : envoyez la confirmation au client et ajoutez le retrait à votre agenda. | 주문을 확정했어요. 고객에게 확정 이메일을 보내고 픽업을 캘린더에 추가하세요.
Commande créée. | 주문을 만들었어요.
Commande modifiée. | 주문을 수정했어요.
Commande remise. Pensez à proposer un avis Google au client. | 전달 완료로 기록했어요. 고객에게 구글 리뷰를 부탁해 보세요.
Commentaire ajouté. | 메모를 추가했어요.
Copie créée : choisissez la date de retrait. | 복사본을 만들었어요. 픽업 날짜를 고르세요.
Demande d'avis notée sur la fiche du client. | 고객 정보에 리뷰 요청을 기록했어요.
Demande d'avis notée. | 리뷰 요청을 기록했어요.
Donnez un nom au nouvel ingrédient. | 새 재료 이름을 적어 주세요.
Donnez un nom à l'ingrédient. | 재료 이름을 적어 주세요.
Données personnelles effacées. Les commandes restent pour les comptes et les statistiques, sans nom ni coordonnées. Pensez aussi aux emails de ce client dans contact@ et, s'il y a des factures, gardez-les (obligation comptable). | 개인 정보를 삭제했어요. 주문은 이름과 연락처 없이 회계와 통계용으로 남아요. contact@에 있는 이 고객의 이메일도 정리하고, 청구서가 있다면 보관하세요 (회계 의무).
Enregistré. | 저장했어요.
Fiche enregistrée. | 저장했어요.
Indiquez un email valide et un nom. | 올바른 이메일 주소와 이름을 적어 주세요.
Ingrédient ajouté. | 재료를 추가했어요.
Ingrédient enregistré. | 재료를 저장했어요.
Les coûts et les allergènes des recettes suivent. | 레시피의 원가와 알레르기 유발 성분도 따라 바뀌어요.
Le lien d'avis doit être un lien Google (https://g.page/r/…/review). | 리뷰 링크는 구글 링크여야 해요 (https://g.page/r/…/review).
Le lien de la fiche technique doit commencer par https:// | 제품 시트 링크는 https://로 시작해야 해요.
Le mot de passe actuel n'est pas le bon. | 현재 비밀번호가 맞지 않아요.
Le nom ne peut pas être vide. | 이름을 비워 둘 수 없어요.
Les deux nouveaux mots de passe ne sont pas identiques. | 두 새 비밀번호가 같지 않아요.
Matomo est relié : les visites du site sont dans Statistiques › Site internet. | Matomo를 연결했어요. 사이트 방문은 통계 › 웹사이트에 있어요.
Matomo n'est plus relié : les chiffres du site ne s'affichent plus ici. | Matomo 연결을 끊었어요. 사이트 숫자는 더 이상 여기에 보이지 않아요.
Miroir arrêté : l'ancienne clé ne marche plus. | 사본을 중지했어요. 이전 키는 더 이상 안 돼요.
Mot de passe changé. Les autres appareils devront se reconnecter. | 비밀번호를 바꿨어요. 다른 기기에서는 다시 로그인해야 해요.
Paiement enregistré. | 결제를 저장했어요.
Pour effacer, tapez EFFACER en majuscules. | 삭제하려면 대문자로 EFFACER를 입력하세요.
Problème résolu. | 문제를 해결됨으로 표시했어요.
Problème signalé : il reste visible partout jusqu'à ce qu'il soit résolu. | 문제를 신고했어요. 해결될 때까지 어디서나 보여요.
Proposition notée. Quand le client accepte par écrit, confirmez la commande. | 견적을 기록했어요. 고객이 글로 수락하면 주문을 확정하세요.
Recette copiée : vérifiez les quantités (les œufs, les fruits entiers…). | 레시피를 복사했어요. 수량을 확인하세요 (달걀, 통과일…).
L'email d'invitation n'a pas pu partir. | 초대 이메일을 보내지 못했어요.
Les nouvelles demandes du site s'ajouteront toutes seules ici (l'email à contact@ continue d'arriver). | 이제 새 사이트 문의가 여기에 자동으로 추가돼요 (contact@로 오는 이메일도 계속 와요).
Les demandes du site ne s'ajoutent plus ici ; elles arrivent toujours par email. | 사이트 문의가 더 이상 여기에 추가되지 않아요. 이메일로는 계속 와요.

# Historique (textes écrits par la gestion)
Fiche client créée | 고객 정보 생성
Demande créée | 문의 생성
Demande envoyée par le formulaire du site | 사이트 문의 양식으로 접수
Reprise des données | 데이터 가져오기
Export complet téléchargé | 전체 데이터 내보내기 다운로드
Mot de passe changé | 비밀번호 변경
Signature des emails ou lien d'avis modifié | 이메일 서명 또는 리뷰 링크 수정
Enregistrement des demandes du site activé | 사이트 문의 자동 저장 켬
Enregistrement des demandes du site arrêté | 사이트 문의 자동 저장 끔
Miroir pour le Drive arrêté | Drive용 사본 중지
Nouvelle clé du miroir pour le Drive | Drive용 사본 새 키
Clé de Matomo retirée (statistiques du site) | Matomo 키 삭제 (사이트 통계)
Matomo relié (statistiques du site) | Matomo 연결 (사이트 통계)
Données du client exportées (droit d'accès ou de portabilité) | 고객 정보 내보내기 (열람권 또는 이동권)
Avis Google demandé | 구글 리뷰 요청
Avis Google reçu | 구글 리뷰 받음
Client passé inactif | 고객 비활성화
Client réactivé | 고객 다시 활성화
demande du client | 고객 요청
fin de conservation | 보관 기간 끝
site | 사이트
système | 시스템

# Mois
janvier | 1월
février | 2월
mars | 3월
avril | 4월
mai | 5월
juin | 6월
juillet | 7월
août | 8월
septembre | 9월
octobre | 10월
novembre | 11월
décembre | 12월
janv. | 1월
févr. | 2월
avr. | 4월
juil. | 7월
sept. | 9월
oct. | 10월
nov. | 11월
déc. | 12월
TXT;
}

/* ---------- Dates, heures, durées ---------- */
/** « 06/11/2026 à 21 h 09 », « 02/09/2026 », « dimanche 1 novembre », « 6 novembre 2026 », « dim. 1 nov. » → coréen. */
function coreen_date(string $s): ?string {
    $s = trim($s);
    if (preg_match('~^(\d{2})/(\d{2})/(\d{4})(?: à (\d{2}) h (\d{2}))?$~', $s, $m)) {
        return $m[3] . '. ' . (int)$m[2] . '. ' . (int)$m[1] . '.' . (isset($m[4]) ? ' ' . $m[4] . ':' . $m[5] : '');
    }
    if (preg_match('/^(?:(\p{L}+\.?) )?(\d{1,2}) (\p{L}+\.?)(?: (\d{4}))?$/u', $s, $m)) {
        $mo = KO_MOIS[mb_strtolower($m[3])] ?? null;
        if (!$mo) return null;
        $jour = '';
        if ($m[1] !== '') {
            $j = mb_strtolower($m[1]);
            if (isset(KO_JOURS[$j])) $jour = ' ' . KO_JOURS[$j];
            elseif (isset(KO_JOURS_COURTS[$j])) $jour = ' (' . KO_JOURS_COURTS[$j] . ')';
            else return null;
        }
        return (($m[4] ?? '') !== '' ? $m[4] . '년 ' : '') . $mo . '월 ' . (int)$m[2] . '일' . $jour;
    }
    return null;
}
/** « il y a 3 h », « il y a 12 j », « le 02/09/2026 » → « 3시간 전 », « 12일 전 », « 2026. 9. 2. ». */
function coreen_relatif(string $s): ?string {
    if (preg_match('/^il y a (\d+) (min|h|j)$/', trim($s), $m)) return $m[1] . ['min' => '분', 'h' => '시간', 'j' => '일'][$m[2]] . ' 전';
    if (preg_match('/^le (.+)$/u', trim($s), $m)) return coreen_date($m[1]);
    return coreen_date($s);
}
/** Le moment d'un retrait : « sam. 7 nov., 09 h 30 », « mardi 10 novembre, après-midi (livraison) ». */
function coreen_retrait(string $s): ?string {
    if (!preg_match('/^(.+?)(?:, (matin|midi|après-midi|soir|\d{2} h \d{2}))?( \(livraison\))?$/u', trim($s), $m)) return null;
    $d = coreen_date($m[1]);
    if ($d === null) return null;
    $q = $m[2] ?? '';
    if ($q !== '') $q = ' ' . (KO_MOMENTS[$q] ?? preg_replace('/^(\d{2}) h (\d{2})$/', '$1:$2', $q));
    return $d . $q . (!empty($m[3]) ? ' (배달)' : '');
}
function coreen_mois(string $mois): ?string { $n = KO_MOIS[mb_strtolower($mois)] ?? null; return $n ? $n . '월' : null; }
/** Une liste d'allergènes écrite en français (« Soja, Lait ») → les noms coréens, avec le mot français entre parenthèses. */
function coreen_allergenes(string $s): ?string {
    $inverse = [];
    foreach (ALLERGENES as $code => $fr) $inverse[$fr] = $code;
    $out = [];
    foreach (explode(', ', $s) as $x) {
        if (!isset($inverse[$x])) return null;
        $out[] = ALLERGENES_KO[$inverse[$x]] ?? $x;
    }
    return implode(', ', $out);
}
/** Les noms de champs de l'historique (« nom, ville » → « 성, 도시 »). */
function coreen_champs(string $s): string {
    return implode(', ', array_map(fn($c) => KO_CHAMPS[trim($c)] ?? trim($c), explode(',', $s)));
}

/* ---------- Phrases qui contiennent un nombre, une date ou un nom ---------- */
function coreen_motifs(): array {
    static $p = null;
    if ($p !== null) return $p;
    $t = fn(string $x): string => coreen_texte($x) ?? $x;
    $date = fn(string $x): ?string => coreen_date($x);
    $E = '[−-]?\d[\d ]*(?:,\d{1,2})? €';
    $N = '\d[\d ]*';
    $ML = 'janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre';
    $p = [
        // Dates seules, moments de retrait, heures, durées
        ['~^\d{2}/\d{2}/\d{4}(?: à \d{2} h \d{2})?$~', fn($m) => coreen_date($m[0])],
        ['/^(?:\p{L}+\.? )?\d{1,2} \p{L}+\.?(?: \d{4})?(?:, (?:matin|midi|après-midi|soir|\d{2} h \d{2}))?(?: \(livraison\))?$/u', fn($m) => coreen_retrait($m[0])],
        ['/^il y a \d+ (?:min|h|j)$/', fn($m) => coreen_relatif($m[0])],
        ['/^(\d{2}) h (\d{2})$/', fn($m) => $m[1] . ':' . $m[2]],
        ['/^(\d+) h$/', fn($m) => $m[1] . '시간'],
        ['/^(\d+) min(?: (\d+) s)?$/', fn($m) => $m[1] . '분' . (isset($m[2]) ? ' ' . $m[2] . '초' : '')],
        ['/^(\d+) s$/', fn($m) => $m[1] . '초'],
        ['/^(\p{Lu}\p{Ll}+) (\d{4})$/u', fn($m) => ($mo = coreen_mois($m[1])) ? $m[2] . '년 ' . $mo : null],
        ['/^(\p{Lu}\p{Ll}+) (\d{4}) : (' . $E . '), (\d+) commande\(s\)$/u', fn($m) => ($mo = coreen_mois($m[1])) ? $m[2] . '년 ' . $mo . ': ' . $m[3] . ', 주문 ' . $m[4] . '건' : null],
        ['~^(\d{1,2})/(\d{1,2})$~', fn($m) => $m[2] . '/' . $m[1]],
        ['/^Du (.+) au (.+)$/u', fn($m) => ($a = $date($m[1])) && ($b = $date($m[2])) ? $a . ' ~ ' . $b : null],
        // Montants et unités
        ['/^(' . $E . ') TTC$/u', fn($m) => $m[1] . ' (부가세 포함)'],
        ['/^(' . $E . ') la pièce$/u', fn($m) => $m[1] . ' / 개'],
        ['/^(' . $E . ') le kg$/u', fn($m) => $m[1] . ' / kg'],
        ['/^(' . $E . ') le litre$/u', fn($m) => $m[1] . ' / L'],
        ['/^dès (' . $E . ')$/u', fn($m) => $m[1] . '부터'],
        ['/^(\d+) tailles · dès (' . $E . ')$/u', fn($m) => '크기 ' . $m[1] . '가지 · ' . $m[2] . '부터'],
        ['/^(\d+) parfums · (' . $E . ') la pièce$/u', fn($m) => '맛 ' . $m[1] . '가지 · ' . $m[2] . ' / 개'],
        ['/^(\d+) tailles$/', fn($m) => '크기 ' . $m[1] . '가지'],
        ['/^(\d+) parfums$/', fn($m) => '맛 ' . $m[1] . '가지'],
        ['/^([\d,]+) pièces?$/', fn($m) => $m[1] . '개'],
        ['/^au moins (\d+) pièces$/', fn($m) => '최소 ' . $m[1] . '개'],
        ['/^([\d ]+) Ko$/', fn($m) => $m[1] . ' KB'],
        ['/^TVA ([\d,]+ %)$/u', fn($m) => '부가세 ' . $m[1]],
        ['/^([\d,]+ %) du prix HT$/u', fn($m) => '부가세 별도 가격의 ' . $m[1]],
        ['/^Coût matières et emballage \((' . $E . ')\)$/u', fn($m) => '재료비와 포장비 (' . $m[1] . ')'],
        ['/^pas encore fixé \(indicatif : (' . $E . ')\)$/u', fn($m) => '아직 정하지 않음 (참고: ' . $m[1] . ')'],
        ['/^acompte (' . $E . ')$/u', fn($m) => '선금 ' . $m[1]],
        ['/^facture (.+)$/u', fn($m) => '청구서 ' . $m[1]],
        ['/^Acompte de (' . $E . ') reçu$/u', fn($m) => '선금 ' . $m[1] . ' 받음'],
        ['/^à encaisser : (' . $E . ')$/u', fn($m) => '받을 금액: ' . $m[1]],
        // Compteurs
        ['/^(\d+) commandes?$/', fn($m) => '주문 ' . $m[1] . '건'],
        ['/^(\d+) commande\(s\) remise\(s\)$/', fn($m) => '전달 완료 주문 ' . $m[1] . '건'],
        ['/^(\d+) en cours$/', fn($m) => '진행 중 ' . $m[1] . '건'],
        ['/^(\d+) en retard$/', fn($m) => $m[1] . '건 늦음'],
        ['/^(\d+) autre\(s\) demande\(s\)$/', fn($m) => '다른 문의 ' . $m[1] . '건'],
        ['/^(\d+) demande\(s\) en tout$/', fn($m) => '전체 문의 ' . $m[1] . '건'],
        ['/^(\d+) demandé\(s\)$/', fn($m) => $m[1] . '건 요청'],
        ['/^(\d+) professionnel\(s\)$/', fn($m) => '업체 ' . $m[1] . '곳'],
        ['/^(\d+) clients?(?:, dont (\d+) professionnels?)?$/', fn($m) => '고객 ' . $m[1] . '명' . (isset($m[2]) ? ', 그중 업체 ' . $m[2] . '곳' : '')],
        ['/^(\d+) demande\(s\) reçue\(s\) en (' . $ML . ')\.$/u', fn($m) => coreen_mois($m[2]) . '에 문의 ' . $m[1] . '건 접수.'],
        ['/^Remises en (' . $ML . ')$/u', fn($m) => coreen_mois($m[1]) . ' 전달 완료'],
        ['/^(\d+) vendu\(s\) en 12 mois$/', fn($m) => '12개월 동안 ' . $m[1] . '개 판매'],
        ['/^(\d+) vendu\(s\)$/', fn($m) => $m[1] . '개 판매'],
        ['/^(\d+) ingrédient\(s\), coût (' . $E . ')$/u', fn($m) => '재료 ' . $m[1] . '개, 원가 ' . $m[2]],
        ['/^(\d+) ingrédient\(s\), avec leur prix et leurs allergènes$/u', fn($m) => '재료 ' . $m[1] . '개 (가격과 알레르기 유발 성분)'],
        ['/^(\d+) ingrédient\(s\) sans prix : le coût est sous-estimé\.$/u', fn($m) => '가격이 없는 재료 ' . $m[1] . '개: 원가가 실제보다 낮게 나와요.'],
        ['/^(\d+) jour\(s\) avant le retrait$/', fn($m) => '픽업 ' . $m[1] . '일 전까지'],
        ['/^(\d+) sur (\d+) en moins de 24 h$/', fn($m) => $m[2] . '건 중 ' . $m[1] . '건 24시간 안에 답장'],
        ['/^(' . $N . ') demande\(s\) envoyée\(s\)$/', fn($m) => '문의 ' . $m[1] . '건'],
        ['/^(' . $N . ') demande\(s\) envoyée\(s\) après un scan$/', fn($m) => '스캔 후 문의 ' . $m[1] . '건'],
        ['/^(' . $N . ') pages vues$/', fn($m) => '페이지뷰 ' . $m[1] . '회'],
        ['/^([\d, ]+ %) des visites, 30 jours$/u', fn($m) => '방문의 ' . $m[1] . ' (30일)'],
        ['/^(' . $N . ') visites?$/', fn($m) => '방문 ' . $m[1] . '회'],
        ['/^(.+) : (' . $N . ') visites?$/u', fn($m) => ($d = coreen_date($m[1])) ? $d . ': 방문 ' . $m[2] . '회' : null],
        ['/^Commandes et demandes \((\d+)\)$/', fn($m) => '주문과 문의 (' . $m[1] . ')'],
        ['/^(\d+) produits · version (.+)$/u', fn($m) => '제품 ' . $m[1] . '개 · 버전 ' . $m[2]],
        ['/^(\d+) enregistrée\(s\)(?:, dernière le (.+))?$/u', fn($m) => $m[1] . '건 저장' . (isset($m[2]) ? ', 최근 ' . ($date($m[2]) ?? $m[2]) : '')],
        ['/^(.+) : (\d+) demande\(s\), (\d+) client\(s\)$/u', fn($m) => ($d = $date($m[1])) ? $d . ': 문의 ' . $m[2] . '건, 고객 ' . $m[3] . '명' : null],
        ['/^(\d+) demande\(s\), (\d+) client\(s\), (\d+) ligne\(s\) de journal$/', fn($m) => '문의 ' . $m[1] . '건, 고객 ' . $m[2] . '명, 기록 ' . $m[3] . '줄'],
        // Repères dans les listes
        ['/^reçue (.+)$/u', fn($m) => ($d = coreen_relatif($m[1])) ? $d . ' 접수' : null],
        ['/^dernier échange (.+)$/u', fn($m) => ($d = coreen_relatif($m[1])) ? '최근 연락 ' . $d : null],
        ['/^dernière le (.+)$/u', fn($m) => ($d = $date($m[1])) ? '최근 주문 ' . $d : null],
        ['/^client depuis le (.+)$/u', fn($m) => ($d = $date($m[1])) ? $d . '부터 고객' : null],
        ['/^vu (.+)$/u', fn($m) => ($d = coreen_relatif($m[1])) ? $d . ' 접속' : null],
        ['/^(Site|Email|Instagram|Téléphone|Marché|Sur place|Restaurant|Autre), le (.+)$/u', fn($m) => ($d = $date($m[2])) ? $t($m[1]) . ', ' . $d : null],
        ['/^Sans réponse depuis (\d+) h$/', fn($m) => $m[1] . '시간째 답장 없음'],
        ['/^Rien depuis (\d+) j$/', fn($m) => $m[1] . '일째 소식 없음'],
        ['/^Recette : (\d+)\/(\d+)$/', fn($m) => '레시피: ' . $m[1] . '/' . $m[2]],
        ['/^marge (\d+) à (\d+) %$/', fn($m) => '마진 ' . ($m[1] === $m[2] ? $m[1] : $m[1] . '~' . $m[2]) . ' %'],
        ['/^Fiche validée le (.+)$/u', fn($m) => ($d = $date($m[1])) ? $d . ' 확인 완료' : null],
        ['/^Allergène absent du site : (.+)$/u', fn($m) => '사이트에 없는 알레르기 유발 성분: ' . (coreen_allergenes($m[1]) ?? $m[1])],
        ['/^Le site indique aussi (.+) : une mention de précaution à garder, ou un ingrédient pas encore saisi dans la recette\.$/u',
            fn($m) => '사이트에는 ' . (coreen_allergenes($m[1]) ?? $m[1]) . '도 적혀 있어요. 주의 문구로 남겨 두거나, 아직 레시피에 입력하지 않은 재료예요.'],
        ['/^(.+) \(hors carte\)$/u', fn($m) => $m[1] . ' (메뉴에 없음)'],
        ['/^(.+) \(variante retirée\)$/u', fn($m) => $m[1] . ' (없어진 옵션)'],
        ['/^(.+) \(contact effacé\)$/u', fn($m) => $m[1] . ' (연락처 삭제됨)'],
        ['/^Plaque : « (.*) »$/u', fn($m) => '초콜릿 플레이트: « ' . $m[1] . ' »'],
        ['/^plaque « (.*) »$/u', fn($m) => '플레이트 « ' . $m[1] . ' »'],
        ['/^(\d+ × .+) \(plaque « (.*) »\)$/u', fn($m) => $m[1] . ' (플레이트 « ' . $m[2] . ' »)'],
        ['/^Personnalisé : (.+)$/us', fn($m) => '맞춤: ' . $m[1]],
        ['/^Pro : (.+)$/us', fn($m) => '업체: ' . $m[1]],
        // Une commande
        ['/^Modifier (.+)$/u', fn($m) => $m[1] . ' 수정'],
        ['/^Retrait programmé : (.+)$/u', fn($m) => ($d = coreen_retrait($m[1])) ? '픽업 예정: ' . $d : null],
        ['/^Remise le (.+)$/u', fn($m) => ($d = $date($m[1])) ? $d . ' 전달 완료' : null],
        ['/^Livrée le (.+)$/u', fn($m) => ($d = $date($m[1])) ? $d . ' 배달 완료' : null],
        ['/^Annulée par le client(?:, (.+))?$/u', fn($m) => '고객이 취소' . (isset($m[1]) ? ': ' . $m[1] : '')],
        ['/^Annulée par Madame Cho(?:, (.+))?$/u', fn($m) => 'Madame Cho가 취소' . (isset($m[1]) ? ': ' . $m[1] : '')],
        ['/^par le client(?:, (.+))?$/u', fn($m) => '고객 요청' . (isset($m[1]) ? ': ' . $m[1] : '')],
        ['/^par Madame Cho(?:, (.+))?$/u', fn($m) => 'Madame Cho 결정' . (isset($m[1]) ? ': ' . $m[1] : '')],
        ['/^Reçue il y a (\d+) h : le site promet une réponse sous 24 h\.$/u', fn($m) => $m[1] . '시간 전에 접수됐어요. 사이트는 24시간 안에 답장한다고 약속해요.'],
        ['/^À répondre avant le (.+)\.$/u', fn($m) => ($d = $date($m[1])) ? $d . '까지 답장하세요.' : null],
        ['/^Proposition envoyée (.+?)( :|\.)$/u', fn($m) => ($d = coreen_relatif($m[1])) ? $d . ' 견적 발송' . ($m[2] === '.' ? '.' : ':') : null],
        ['/^sans nouvelles depuis (\d+) jours, une relance ou « sans suite » \?$/u', fn($m) => $m[1] . '일째 소식이 없어요. 다시 연락할까요, ‘진행 안 됨’으로 할까요?'],
        ['/^Avis Google reçu le (.+)\. Merci !$/u', fn($m) => ($d = $date($m[1])) ? $d . ' 구글 리뷰 받음. 감사해요!' : null],
        ['/^Avis Google demandé le (.+)\.$/u', fn($m) => ($d = $date($m[1])) ? $d . ' 구글 리뷰 요청함.' : null],
        ['/^Avis reçu le (.+)\.$/u', fn($m) => ($d = $date($m[1])) ? $d . ' 리뷰 받음.' : null],
        ['/^Avis demandé le (.+)\.$/u', fn($m) => ($d = $date($m[1])) ? $d . ' 리뷰 요청함.' : null],
        ['/^Données personnelles effacées le (.+)\. Il ne reste que le numéro, les dates et les montants des commandes\.$/u',
            fn($m) => ($d = $date($m[1])) ? $d . ' 개인 정보 삭제됨. 고객 번호, 주문 날짜와 금액만 남아 있어요.' : null],
        ['/^Aucune commande ne correspond à « (.*) »\.$/u', fn($m) => '« ' . $m[1] . ' »에 맞는 주문이 없어요.'],
        ['/^Aucun client ne correspond à « (.*) »\.$/u', fn($m) => '« ' . $m[1] . ' »에 맞는 고객이 없어요.'],
        ['/^La référence « (.*) » n\'est pas dans la gestion\. (.+)$/u', fn($m) => '« ' . $m[1] . ' » 주문 번호는 관리 화면에 없어요. ' . $t($m[2])],
        ['/^Le client (CL-\d+) n\'existe pas\.$/u', fn($m) => $m[1] . ' 고객은 없어요.'],
        ['/^Problème : (.*)$/us', fn($m) => '문제: ' . $m[1]],
        ['/^Problème signalé : (.*)$/us', fn($m) => '문제 신고: ' . $m[1]],
        ['/^Problème résolu :\s?(.*)$/us', fn($m) => '문제 해결' . ($m[1] !== '' ? ': ' . $m[1] : '')],
        ['/^(\d)\. (.+)$/u', fn($m) => $m[1] . '. ' . $t($m[2])],
        // Connexion et compte
        ['/^Pour (\S+@\S+)\. Au moins (\d+) caractères : une petite phrase est plus facile à retenir et plus sûre\.$/u',
            fn($m) => $m[1] . ' 계정이에요. 최소 ' . $m[2] . '자. 짧은 문장이 기억하기 쉽고 더 안전해요.'],
        ['/^Le mot de passe doit faire au moins (\d+) caractères \(une phrase facile à retenir, par exemple\)\.$/u', fn($m) => '비밀번호는 최소 ' . $m[1] . '자여야 해요 (기억하기 쉬운 문장 등).'],
        ['/^Au moins (\d+) caractères\. Gardez-le dans Bitwarden\. Oublié \? « Mot de passe oublié » sur la page de connexion envoie un lien à (.+)\.$/u',
            fn($m) => '최소 ' . $m[1] . '자. Bitwarden에 저장해 두세요. 잊어버리면 로그인 화면의 ‘처음 접속하거나 비밀번호를 잊으셨나요?’에서 ' . $m[2] . ' 주소로 링크를 받을 수 있어요.'],
        ['/^Essai : l\'email qui serait parti à (.+)$/u', fn($m) => '테스트: ' . $m[1] . ' 주소로 보냈을 이메일'],
        ['/^Gestion Madame Cho · version du (.+)$/u', fn($m) => 'Madame Cho 관리 · ' . ($date($m[1]) ?? $m[1]) . ' 버전'],
        // Statistiques du site
        ['/^Meilleur jour : (.+), (' . $N . ') visites?\.$/u', fn($m) => ($d = $date($m[1])) ? '가장 많이 온 날: ' . $d . ', 방문 ' . $m[2] . '회.' : null],
        ['/^Aujourd\'hui : (' . $N . ') visites?\.$/u', fn($m) => '오늘: 방문 ' . $m[1] . '회.'],
        ['/^Matomo mesure le site depuis le (.+) : les jours d\'avant sont vides\.$/u', fn($m) => ($d = $date($m[1])) ? 'Matomo는 ' . $d . '부터 사이트를 측정해요. 그 전 날짜는 비어 있어요.' : null],
        ['/^En détail : (.+)$/u', fn($m) => '자세히: ' . $m[1]],
        ['/^Matomo ne répond pas, code (\d+)$/', fn($m) => 'Matomo가 응답하지 않아요 (코드 ' . $m[1] . ')'],
        ['/^Lecture impossible pour l\'instant : (.+?)\.( Réessayez dans quelques minutes\.)?$/u', fn($m) => '지금은 읽을 수 없어요: ' . $t($m[1]) . '.' . (!empty($m[2]) ? ' 몇 분 뒤에 다시 해 보세요.' : '')],
        ['/^Lecture impossible pour l\'instant \((.+)\) : voici les chiffres lus à (\d{2}) h (\d{2})\.$/u', fn($m) => '지금은 읽을 수 없어요 (' . $t($m[1]) . '). ' . $m[2] . ':' . $m[3] . '에 읽은 숫자예요.'],
        ['/^Chiffres de Matomo, lus à (\d{2}) h (\d{2}) et gardés un quart d\'heure\. Mesure sans cookie : les visiteurs qui refusent la mesure ne sont pas comptés\.$/u',
            fn($m) => 'Matomo 숫자예요. ' . $m[1] . ':' . $m[2] . '에 읽었고 15분 동안 보관해요. 쿠키 없이 측정하고, 측정을 거부한 방문자는 세지 않아요.'],
        // Réglages
        ['/^La tâche de nuit ne passe plus depuis le (.+) : prévenez Olivier \(tâche planifiée OVH\)\.$/u', fn($m) => ($d = $date($m[1])) ? $d . '부터 야간 작업이 돌지 않아요. Olivier에게 알려 주세요 (OVH 예약 작업).' : null],
        // Messages après une action
        ['/^Étape changée : (.*)\.$/u', fn($m) => '단계를 바꿨어요: ' . $t($m[1]) . '.'],
        ['/^Le numéro (.+) ne correspond à aucun autre client\.$/u', fn($m) => $m[1] . ' 번호의 다른 고객이 없어요.'],
        ['/^Le doublon (.+) est fusionné dans cette fiche\.$/u', fn($m) => '중복 고객 ' . $m[1] . '을(를) 이 고객 정보에 합쳤어요.'],
        ['/^C\'est la même personne \? On garde la fiche (CL-\d+), la plus ancienne, avec les commandes et l\'historique des deux\.$/u',
            fn($m) => '같은 사람인가요? 더 오래된 ' . $m[1] . ' 고객 정보를 남기고, 두 고객의 주문과 기록을 모두 옮겨요.'],
        ['/^Les deux fiches sont fusionnées dans (CL-\d+)\.$/u', fn($m) => '두 고객 정보를 ' . $m[1] . '에 합쳤어요.'],
        // Restaurants : livraisons, relevé du mois, gamme pro
        ['/^(' . $E . ') HT$/u', fn($m) => $m[1] . ' (부가세 별도)'],
        ['/^(' . $E . ') HT ·$/u', fn($m) => $m[1] . ' (부가세 별도) ·'],
        ['/^· (' . $E . ') HT$/u', fn($m) => '· ' . $m[1] . ' (부가세 별도)'],
        ['/^Recette · coût (' . $E . ')(?: · marge (\d+) %)?$/u', fn($m) => '레시피 · 원가 ' . $m[1] . (isset($m[2]) ? ' · 마진 ' . $m[2] . ' %' : '')],
        ['/^(\d+) livrée\(s\)$/', fn($m) => '배달 완료 ' . $m[1] . '건'],
        ['/^(\d+) livraison\(s\) pas encore marquée\(s\) « Livrée » : vérifiez-les avant de faire la facture, elles ne sont pas comptées\.$/u',
            fn($m) => '아직 ‘배달 완료’로 표시하지 않은 배달이 ' . $m[1] . '건 있어요. 청구서를 만들기 전에 확인하세요. 합계에는 들어가지 않아요.'],
        ['/^(\d+) livraison\(s\) déjà facturée\(s\) \((.+)\) : pas comptées dans le total\.$/u', fn($m) => '이미 청구한 배달 ' . $m[1] . '건 (' . $m[2] . '): 합계에 들어가지 않아요.'],
        ['/^(\d+) à facturer$/', fn($m) => '청구할 배달 ' . $m[1] . '건'],
        ['/^(\d+) livraison\(s\) sur (\d+) facturée\(s\)\.$/u', fn($m) => '배달 ' . $m[2] . '건 중 ' . $m[1] . '건 청구함.'],
        ['/^(\d+) livraison\(s\) · (' . $E . ') HT · (' . $E . ') TTC$/u', fn($m) => '배달 ' . $m[1] . '건 · ' . $m[2] . ' (부가세 별도) · ' . $m[3] . ' (부가세 포함)'],
        ['/^Les livraisons \((\d+)\)$/', fn($m) => '배달 (' . $m[1] . ')'],
        ['/^(\d+) livraison\(s\)$/', fn($m) => '배달 ' . $m[1] . '건'],
        ['/^Noter la facture sur les (\d+) livraison\(s\) à facturer$/u', fn($m) => '청구할 배달 ' . $m[1] . '건에 청구서 적기'],
        ['/^(\d+) livraison\(s\) notée\(s\)(?: avec la facture (.+?))?(, payée\(s\))?\.$/u',
            fn($m) => '배달 ' . $m[1] . '건에 적었어요' . (($m[2] ?? '') !== '' ? ': 청구서 ' . $m[2] : '') . (!empty($m[3]) ? ', 결제 완료' : '') . '.'],
        ['/^[Ff]acture (.+), payée\.?$/u', fn($m) => '청구서 ' . $m[1] . ', 결제 완료'],
        ['/^[Ff]acture (.+), pas encore payée\.?$/u', fn($m) => '청구서 ' . $m[1] . ', 아직 결제 안 됨'],
        ['/^Relevé de (' . $ML . ') (\d{4})$/u', fn($m) => $m[2] . '년 ' . coreen_mois($m[1]) . ' 내역서'],
        ['/^Voir le relevé de (' . $ML . ') (\d{4})$/u', fn($m) => $m[2] . '년 ' . coreen_mois($m[1]) . ' 내역서 보기'],
        ['/^Aucune livraison en (' . $ML . ') (\d{4})\.$/u', fn($m) => $m[2] . '년 ' . coreen_mois($m[1]) . '에는 배달이 없어요.'],
        ['/^‹ (' . $ML . ') (\d{4})$/u', fn($m) => '‹ ' . $m[2] . '년 ' . coreen_mois($m[1])],
        ['/^(' . $ML . ') (\d{4}) ›$/u', fn($m) => $m[2] . '년 ' . coreen_mois($m[1]) . ' ›'],
        ['/^Livraison prévue : (.+)$/u', fn($m) => ($r = coreen_retrait($m[1])) ? '배달 예정: ' . $r : null],
        ['/^Livrée le (.+)$/u', fn($m) => ($d = $date($m[1])) ? $d . ' 배달 완료' : null],
        ['/^Commande créée, sur la fiche de (.+) \((CL-\d+)\), retrouvée par son (email|téléphone)\.$/u',
            fn($m) => '주문을 만들었어요. ' . ($m[3] === 'email' ? '이메일' : '전화번호') . '로 이미 아는 고객이라 ' . $m[1] . ' (' . $m[2] . ') 고객 정보에 넣었어요.'],
        ['/^Ce client a encore (\d+) commande\(s\) en cours : terminez-les ou annulez-les avant d\'effacer\.$/u', fn($m) => '이 고객은 아직 진행 중인 주문이 ' . $m[1] . '건 있어요. 삭제하기 전에 끝내거나 취소하세요.'],
        ['/^La recette « (.*) » est vide\.$/u', fn($m) => '« ' . $m[1] . ' » 레시피가 비어 있어요.'],
        ['/^« (.*) » existe déjà\.$/u', fn($m) => '« ' . $m[1] . ' »은(는) 이미 있어요.'],
        ['/^Invitation envoyée à (.+) \(lien valable 3 jours\)\.$/u', fn($m) => $m[1] . ' 주소로 초대를 보냈어요 (링크는 3일 동안 유효해요).'],
        ['/^Fait : (\d+) demande\(s\) anonymisée\(s\), (\d+) client\(s\) effacé\(s\), (\d+) ligne\(s\) de journal supprimée\(s\)\.$/u',
            fn($m) => '완료: 문의 ' . $m[1] . '건 익명 처리, 고객 ' . $m[2] . '명 삭제, 기록 ' . $m[3] . '줄 삭제.'],
        ['/^Clé non enregistrée : (.+)\.$/u', fn($m) => '키를 저장하지 않았어요: ' . $t($m[1]) . '.'],
        ['/^Reprise : (\d+) client\(s\) créé\(s\), (\d+) déjà connu\(s\), (\d+) commande\(s\) créée\(s\), (\d+) déjà présente\(s\)\.$/u',
            fn($m) => '가져오기: 고객 ' . $m[1] . '명 생성, ' . $m[2] . '명은 이미 있음, 주문 ' . $m[3] . '건 생성, ' . $m[4] . '건은 이미 있음.'],
        ['/^À revoir : (.+)$/u', fn($m) => '다시 볼 것: ' . $m[1]],
        ['/^La reprise a échoué : rien n\'a été enregistré\.(.*)$/u', fn($m) => '가져오기에 실패했어요. 아무것도 저장되지 않았어요.' . $m[1]],
        // Assistant et rapports
        ['/^Pas de réponse : (.+)\.$/u', fn($m) => '답이 없어요: ' . $t($m[1]) . '.'],
        ['/^demande refusée par l\'API(?: \((.+)\))?$/u', fn($m) => 'API가 요청을 거부했어요' . (isset($m[1]) ? ' (' . $m[1] . ')' : '')],
        ['/^réponse inattendue de l\'API \(code (\d+)\)$/u', fn($m) => 'API에서 뜻밖의 응답이 왔어요 (코드 ' . $m[1] . ')'],
        ['/^le nombre de questions du jour est atteint \((\d+)\) : à demain, ou prévenez Olivier$/u', fn($m) => '오늘 질문 한도(' . $m[1] . '개)에 도달했어요. 내일 다시 하거나 Olivier에게 알려 주세요'],
        ['/^Envoyé le (.+) à contact@madamecho\.com$/u', fn($m) => ($d = $date($m[1])) ? $d . ' contact@madamecho.com으로 보냄' : null],
        ['/^Ce mois-ci : (\d+) question\(s\) · ≈ (.+) \$$/u', fn($m) => '이번 달: 질문 ' . $m[1] . '건 · ≈ ' . $m[2] . ' $'],
        ['/^Ce mois-ci : (\d+) question\(s\)$/u', fn($m) => '이번 달: 질문 ' . $m[1] . '건'],
        ['/^(\d+) question\(s\)$/u', fn($m) => '질문 ' . $m[1] . '건'],
        ['/^le plafond du mois est atteint \((\d+) \$\) : prévenez Olivier$/u', fn($m) => '이번 달 한도(' . $m[1] . ' $)에 도달했어요. Olivier에게 알려 주세요'],
        ['/^Plafond enregistré : (\d+) \$ par mois\.$/u', fn($m) => '한도를 저장했어요: 한 달 ' . $m[1] . ' $.'],
        ['/^(\d+) question\(s\) · ≈ (.+) \$$/u', fn($m) => '질문 ' . $m[1] . '건 · ≈ ' . $m[2] . ' $'],
        ['/^Rapport envoyé à Olivier : (.+)$/u', fn($m) => 'Olivier에게 보고서 보냄: ' . $m[1]],
        // Historique
        ['/^Fiche modifiée : (.+)$/u', fn($m) => '고객 정보 수정: ' . coreen_champs($m[1])],
        ['/^Commande modifiée : (.+)$/u', fn($m) => '주문 수정: ' . coreen_champs($m[1])],
        ['/^Fiche produit modifiée : (.+)$/u', fn($m) => '제품 시트 수정: ' . coreen_champs($m[1])],
        ['/^Doublon (.+) fusionné dans cette fiche$/u', fn($m) => '중복 고객 ' . $m[1] . ' 합침'],
        ['/^Pas la même personne que (CL-\d+)$/u', fn($m) => $m[1] . ' 고객과 다른 사람'],
        ['/^Gamme pro : (.+)$/u', fn($m) => '업체용 제품: ' . str_replace([' ajouté, ', ' HT', 'prix ', '(ancien nom : '], [' 추가, ', ' (부가세 별도)', '가격 ', '(이전 이름: '], $m[1])],
        ['/^Ajouté à la fiche : (.+)$/u', fn($m) => '고객 정보에 추가: ' . preg_replace(['/(^|, )email /u', '/(^|, )téléphone /u'], ['$1이메일 ', '$1전화번호 '], $m[1])],
        ['/^Données personnelles effacées : (.+)$/u', fn($m) => '개인 정보 삭제: ' . $t($m[1])],
        ['/^Commande saisie à la main \((.*)\)$/u', fn($m) => '직접 입력한 주문 (' . $t($m[1]) . ')'],
        ['/^Copie de (.+)$/u', fn($m) => $m[1] . ' 복사본'],
        ['/^Purge : (\d+) demande\(s\) anonymisée\(s\), (\d+) client\(s\) effacé\(s\), (\d+) ligne\(s\) de journal$/u', fn($m) => '정리: 문의 ' . $m[1] . '건 익명 처리, 고객 ' . $m[2] . '명 삭제, 기록 ' . $m[3] . '줄 삭제'],
        ['/^Reprise : (\d+) client\(s\) créé\(s\), (\d+) repris, (\d+) commande\(s\) créée\(s\), (\d+) déjà présente\(s\)$/u', fn($m) => '가져오기: 고객 ' . $m[1] . '명 생성, ' . $m[2] . '명 기존, 주문 ' . $m[3] . '건 생성, ' . $m[4] . '건은 이미 있음'],
        ['/^Export CSV des (commandes|clients)$/u', fn($m) => ($m[1] === 'clients' ? '고객' : '주문') . ' CSV 내보내기'],
        ['/^Invitation envoyée à (.+)$/u', fn($m) => $m[1] . ' 주소로 초대 보냄'],
        ['/^Compte de (.+) créé \(première connexion\)$/u', fn($m) => $m[1] . ' 계정 생성 (첫 접속)'],
        ['/^Compte (.+) (réactivé|désactivé)$/u', fn($m) => $m[1] . ' 계정 ' . ($m[2] === 'réactivé' ? '다시 활성화' : '비활성화')],
        ['/^Mot de passe choisi par (.+)$/u', fn($m) => $m[1] . ' 비밀번호 설정'],
        ['/^Recette (.+) : (.+) ajouté$/u', fn($m) => '레시피 ' . $m[1] . ': ' . $m[2] . ' 추가'],
        ['/^Recette (.+) : (.+) retiré$/u', fn($m) => '레시피 ' . $m[1] . ': ' . $m[2] . ' 뺌'],
        ['/^Recette (.+) copiée de (.+) × (.+)$/u', fn($m) => '레시피 ' . $m[1] . ': ' . $m[2] . '에서 복사 (× ' . $m[3] . ')'],
        ['/^(.+?) : (?:prix (.+?) → (' . $E . '))?(?:, )?(?:allergènes : (.+))?$/u', function ($m) {
            if (($m[2] ?? '') === '' && ($m[4] ?? '') === '') return null;
            $x = [];
            if (($m[2] ?? '') !== '') $x[] = '가격 ' . $m[2] . ' → ' . $m[3];
            if (($m[4] ?? '') !== '') $x[] = '알레르기 유발 성분: ' . ($m[4] === 'aucun' ? '없음' : (coreen_allergenes($m[4]) ?? $m[4]));
            return $m[1] . ': ' . implode(', ', $x);
        }],
    ];
    return $p;
}

/** La traduction d'un texte de l'interface, ou null s'il n'est pas connu (il reste alors en français). */
function coreen_texte(string $s): ?string {
    $r = coreen_traduction($s);
    return $r === null ? null : (preg_replace('/(\d) (%|€)/u', "$1\u{00A0}$2", $r) ?? $r);   // « 68 % », « 28 € » : jamais coupés
}
function coreen_traduction(string $s): ?string {
    $s = coreen_cle($s);
    if ($s === '' || !preg_match('/\p{L}/u', $s)) return null;
    $d = coreen_dico();
    if (isset($d[$s])) return $d[$s];
    if (($r = coreen_motif($s)) !== null) return $r;
    // Morceaux séparés par « · » (une référence, un canal, une date…)
    if (str_contains($s, '·')) {
        $m = preg_split('/(\s*·\s*)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $ok = false;
        foreach ($m as $i => $x) {
            if ($i % 2 || $x === '') continue;
            $y = $d[$x] ?? coreen_motif($x);
            if ($y !== null) { $m[$i] = $y; $ok = true; }
        }
        if ($ok) return implode('', $m);
    }
    // Plusieurs phrases
    if (preg_match('/[.!?] \p{Lu}/u', $s)) {
        $ph = preg_split('/(?<=[.!?])\s+(?=\p{Lu})/u', $s) ?: [];
        if (count($ph) > 1) {
            $ok = false;
            foreach ($ph as $i => $x) { $y = $d[$x] ?? coreen_motif($x); if ($y !== null) { $ph[$i] = $y; $ok = true; } }
            if ($ok) return implode(' ', $ph);
        }
    }
    return null;
}
function coreen_motif(string $s): ?string {
    foreach (coreen_motifs() as [$re, $f]) {
        if (preg_match($re, $s, $m)) {
            $r = $f($m);
            if ($r !== null) return $r;
        }
    }
    return null;
}

/* ---------- La page finie ---------- */
/** Traduit les textes et quelques attributs (placeholder, aria-label, title, alt, data-confirmer) hors des zones protégées ; jamais « label » (les noms des gâteaux dans les listes). */
function coreen_html(string $html): string {
    $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    if (!$parts) return $html;
    $vides = ['area' => 1, 'base' => 1, 'br' => 1, 'col' => 1, 'embed' => 1, 'hr' => 1, 'img' => 1, 'input' => 1, 'link' => 1, 'meta' => 1, 'source' => 1, 'track' => 1, 'wbr' => 1];
    $fermes = ['textarea' => 1, 'pre' => 1, 'script' => 1, 'style' => 1, 'code' => 1];
    $pile = [];       // éléments ouverts : [nom, protégé, titre de la page]
    $proteges = 0;
    $tete = false;
    foreach ($parts as $i => $p) {
        if ($p[0] === '<' && isset($p[1]) && $p[1] !== ' ') {
            if ($p[1] === '!' || $p[1] === '?') continue;
            if ($p[1] === '/') {
                $nom = strtolower(trim(substr($p, 2, -1)));
                for ($j = count($pile) - 1; $j >= 0; $j--) {
                    if ($pile[$j][0] !== $nom) continue;
                    while (count($pile) > $j) { $x = array_pop($pile); if ($x[1]) $proteges--; }
                    break;
                }
                if ($nom === 'head') $tete = false;
                continue;
            }
            if (!preg_match('/^<([a-zA-Z][a-zA-Z0-9\-]*)/', $p, $m)) continue;
            $nom = strtolower($m[1]);
            if ($nom === 'head') $tete = true;
            $protege = isset($fermes[$nom]) || str_contains($p, ' translate="no"');
            if (!$proteges && !$protege) $parts[$i] = coreen_attributs($p);
            if (!isset($vides[$nom]) && !str_ends_with($p, '/>')) {
                $pile[] = [$nom, $protege, $tete && $nom === 'title'];
                if ($protege) $proteges++;
            }
            continue;
        }
        if ($proteges || trim($p) === '') continue;
        $haut = $pile ? $pile[count($pile) - 1] : null;
        $parts[$i] = $haut && $haut[2] ? coreen_titre($p) : coreen_noeud($p);
    }
    $html = implode('', $parts);
    return preg_replace('/<html lang="fr"/', '<html lang="ko"', $html, 1) ?? $html;
}
function coreen_noeud(string $t): string {
    if (!preg_match('/\p{L}/u', $t) || !preg_match('/^(\s*)(.*?)(\s*)$/su', $t, $m)) return $t;
    $k = coreen_texte(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return $k === null ? $t : $m[1] . htmlspecialchars($k, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') . $m[3];
}
function coreen_titre(string $t): string {
    $s = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $fin = ' · Gestion Madame Cho';
    if (str_ends_with($s, $fin)) { $x = substr($s, 0, -strlen($fin)); $s = (coreen_texte($x) ?? $x) . ' · Madame Cho 관리'; }
    else $s = coreen_texte($s) ?? $s;
    return htmlspecialchars($s, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function coreen_attributs(string $tag): string {
    if (!preg_match('/\s(?:placeholder|aria-label|title|alt|data-confirmer)="/', $tag)) return $tag;
    return preg_replace_callback('/(\s(?:placeholder|aria-label|title|alt|data-confirmer)=")([^"]*)(")/', function ($m) {
        $k = coreen_texte(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $k === null ? $m[0] : $m[1] . htmlspecialchars($k, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $m[3];
    }, $tag) ?? $tag;
}
