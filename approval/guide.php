<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageUser = current_user($pdo);
$pageTitle = '사용 가이드';
require __DIR__ . '/includes/header.php';
?>
<div class="guide-shell">
    <section class="card guide-hero">
        <span class="badge badge-blue">상세 사용 안내</span>
        <h2>처음 가입부터 문서 기안, 결재, 운영 설정까지 한 번에 정리한 사용 가이드</h2>
        <p>이 페이지는 처음 사용하는 회사 대표, 위임 운영자, 일반 사용자, 최상위 관리자까지 모두 볼 수 있도록 만든 안내 페이지입니다. 아래 항목을 클릭하면 해당 설명이 펼쳐집니다.</p>
        <div class="guide-actions">
            <?php if (!$pageUser): ?>
                <a href="<?= e(base_url('company_register.php')) ?>" class="btn btn-primary">기업가입 시작</a>
                <a href="<?= e(base_url('register.php')) ?>" class="btn btn-secondary">개인 회원가입</a>
                <a href="<?= e(base_url('login.php')) ?>" class="btn btn-outline">로그인</a>
            <?php else: ?>
                <a href="<?= e(base_url('document_create.php')) ?>" class="btn btn-primary">기안서 작성</a>
                <a href="<?= e(base_url('documents.php?view=all')) ?>" class="btn btn-secondary">기안서 통합</a>
                <?php if (can_manage_members($pageUser) || is_admin($pageUser)): ?>
                    <a href="<?= e(base_url('admin/company_settings.php')) ?>" class="btn btn-outline">회사 설정</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="guide-summary">
        <div class="guide-stat"><strong>1단계</strong><span>기업가입 또는 개인 회원가입</span></div>
        <div class="guide-stat"><strong>2단계</strong><span>로그인 후 권한과 메뉴 확인</span></div>
        <div class="guide-stat"><strong>3단계</strong><span>문서 분류, 템플릿, 결재자 설정</span></div>
        <div class="guide-stat"><strong>4단계</strong><span>기안, 결재, 반려, 재기안 운영</span></div>
    </section>

    <section class="card">
        <div class="section-head"><h3 style="margin:0;">빠른 목차</h3><span class="badge badge-gray">클릭하면 아래 설명을 펼쳐 볼 수 있습니다</span></div>
        <div class="guide-index">
            <a href="#guide-company" class="guide-anchor">기업가입 하는 법<small>회사 등록, 회사코드 발급, 회사 관리자 생성</small></a>
            <a href="#guide-code" class="guide-anchor">회사코드 찾는 법<small>회사이름, 대표자명, 대표 전화번호로 조회</small></a>
            <a href="#guide-user-signup" class="guide-anchor">개인 회원가입 하는 법<small>회사코드 확인 후 이름, 이메일, 전화번호, 비밀번호로 가입</small></a>
            <a href="#guide-login" class="guide-anchor">로그인 하는 법<small>일반 회원, 회사 관리자, 최상위 관리자 로그인 방식</small></a>
            <a href="#guide-roles" class="guide-anchor">권한과 레벨 이해하기<small>레벨은 로직용, 직급명은 표시용</small></a>
            <a href="#guide-company-settings" class="guide-anchor">회사 설정 하는 법<small>문서 분류, 템플릿, 위임 권한, 플랜 관리</small></a>
            <a href="#guide-template" class="guide-anchor">결재 템플릿 만드는 법<small>결재선, 참조자, 열람자, 양식까지 저장</small></a>
            <a href="#guide-draft" class="guide-anchor">기안서 작성하는 법<small>템플릿 선택, 첨부, 참조자, 제출</small></a>
            <a href="#guide-approval" class="guide-anchor">결재 흐름 이해하기<small>레벨별 실제 승인 규칙과 자동 승인 조건</small></a>
            <a href="#guide-reject" class="guide-anchor">반려와 재기안 하는 법<small>수정, 재기안, 비교보기</small></a>
            <a href="#guide-notice" class="guide-anchor">공지사항 운영하는 법<small>전체 공지, 기업 공지, 공지/일반글, 조회수</small></a>
            <a href="#guide-trash" class="guide-anchor">휴지통과 복구/비우기<small>삭제, 복구, 영구 삭제</small></a>
            <a href="#guide-notification" class="guide-anchor">알림과 독촉 기능<small>결재 요청, 반려, 완료, 알림 재전송</small></a>
            <a href="#guide-password" class="guide-anchor">비밀번호 재설정<small>회사코드와 본인정보 확인 후 재설정</small></a>
            <a href="#guide-mobile" class="guide-anchor">모바일에서 쓰는 법<small>하단 고정 버튼, 빠른 승인 흐름</small></a>
            
        </div>
    </section>

    <section class="guide-accordion">
        <?php
        $sections = [
            ['id'=>'guide-company','title'=>'기업가입 하는 법','summary'=>'회사 등록, 6자리 회사코드 발급, 회사 대표 계정 생성 과정입니다.','html'=>'<div class="guide-block"><h4>언제 쓰는 기능인가</h4><p>아직 회사가 이 서비스에 등록되지 않았을 때 사용합니다. 한 번 기업가입을 하면 회사 단위 공간이 만들어지고, 그 회사에 속한 문서와 가입자, 공지사항, 설정값이 분리됩니다.</p><h4>입력 항목</h4><ul><li>회사이름</li><li>대표자명</li><li>대표 전화번호</li><li>회사 관리자 이메일(선택)</li><li>회사 관리자 비밀번호</li></ul><div class="guide-steps"><div class="guide-step"><strong>1. 기업가입 페이지 이동</strong><span>로그인 화면 또는 사용 가이드 상단 버튼에서 기업가입을 누릅니다.</span></div><div class="guide-step"><strong>2. 회사 정보 입력</strong><span>회사이름, 대표자명, 대표 전화번호를 정확히 입력합니다. 회사코드 찾기에서 이 정보가 그대로 쓰입니다.</span></div><div class="guide-step"><strong>3. 회사 관리자 생성</strong><span>입력한 비밀번호로 회사 대표 계정이 생성되고, 가입 후 회사코드가 발급됩니다.</span></div></div><div class="guide-tip"><strong>운영 팁</strong><br>대표 전화번호와 대표자명을 대충 넣으면 나중에 회사코드 찾기에서 일치하지 않아 조회가 안 됩니다.</div></div>'],
            ['id'=>'guide-code','title'=>'회사코드 찾는 법','summary'=>'회사코드를 잊었을 때 조회하는 방법입니다.','html'=>'<div class="guide-block"><h4>조회 조건</h4><p>회사이름, 대표자명, 대표 전화번호가 모두 일치해야 회사코드가 조회됩니다.</p><ol><li>회사코드 찾기 페이지로 이동합니다.</li><li>기업가입 당시 입력한 회사이름, 대표자명, 대표 전화번호를 입력합니다.</li><li>정확히 일치하면 6자리 회사코드가 표시됩니다.</li><li>조회된 회사코드는 개인 회원가입과 일반 로그인에 사용합니다.</li></ol><div class="guide-warn"><strong>주의</strong><br>띄어쓰기나 대표 전화번호가 다르면 조회가 실패할 수 있습니다.</div></div>'],
            ['id'=>'guide-user-signup','title'=>'개인 회원가입 하는 법','summary'=>'회사코드를 가진 직원, 구성원이 개인 계정을 생성하는 과정입니다.','html'=>'<div class="guide-block"><h4>회원가입 전 준비물</h4><ul><li>회사코드</li><li>본인 이름</li><li>이메일</li><li>전화번호</li><li>비밀번호</li></ul><ol><li>개인 회원가입 페이지로 이동합니다.</li><li>회사코드를 입력하고 회사명이 맞는지 확인합니다.</li><li>이름, 이메일, 전화번호, 비밀번호를 입력합니다.</li><li>가입이 완료되면 회사 대표 계정이 그 사용자의 레벨과 직급명을 설정합니다.</li></ol><div class="guide-tip"><strong>중요</strong><br>개인 회원가입 시에는 레벨과 직급명을 직접 입력하지 않습니다.</div></div>'],
            ['id'=>'guide-login','title'=>'로그인 하는 법','summary'=>'일반 회원, 회사 관리자, 최상위 관리자의 로그인 방식이 다릅니다.','html'=>'<div class="guide-block"><table class="guide-table"><tr><th>로그인 유형</th><th>입력값</th><th>설명</th></tr><tr><td>일반 회원</td><td>회사코드 + 전화번호 또는 이메일 + 비밀번호</td><td>회사코드는 필수입니다.</td></tr><tr><td>회사 관리자</td><td>회사코드 + 전화번호 또는 이메일 + 비밀번호</td><td>대표 계정도 같은 방식으로 로그인합니다.</td></tr><tr><td>최상위 관리자</td><td>관리자 아이디 + 비밀번호</td><td>회사코드는 비워 둡니다.</td></tr></table><h4>로그인 실패 시 확인할 것</h4><ul><li>회사코드가 맞는지</li><li>비활성화된 계정인지</li><li>비밀번호 오입력 누적으로 잠금 상태인지</li></ul></div>'],
            ['id'=>'guide-roles','title'=>'권한과 레벨 이해하기','summary'=>'레벨은 결재 규칙, 직급명은 화면 표시용입니다.','html'=>'<div class="guide-block"><ul><li><b>레벨</b>: 결재 로직용 숫자. 일반 화면에서는 보이지 않습니다.</li><li><b>직급명</b>: 화면 표시용 텍스트. 일반 사용자에게는 이 값만 보입니다.</li><li><b>회사 관리자</b>: 자기 회사 회원 관리, 설정, 공지 운영 가능</li><li><b>위임 운영자</b>: 대표가 부여한 운영 권한만 사용 가능</li><li><b>최상위 관리자</b>: 모든 회사와 전체 내역 조회 가능</li></ul><div class="guide-tip"><strong>예시</strong><br>두 사람이 모두 Level 5여도 한 사람은 직급명이 팀장, 다른 사람은 검수총괄일 수 있습니다.</div></div>'],
            ['id'=>'guide-company-settings','title'=>'회사 설정 하는 법','summary'=>'문서 분류, 템플릿, 위임 권한, 플랜, 배너/팝업을 관리하는 화면입니다.','html'=>'<div class="guide-block"><h4>회사 설정 화면에서 할 수 있는 것</h4><ul><li>문서 분류 추가, 수정, 활성화, 비활성화, 삭제</li><li>결재 템플릿 생성 및 관리</li><li>회사 상태, 플랜, 광고 제거 기간 확인</li><li>일괄 승인 허용 여부 설정</li><li>최근 활동 로그 확인</li><li>위임 운영 권한 부여</li></ul><h4>문서 분류 관리 방법</h4><ol><li>회사 설정으로 이동합니다.</li><li>문서 분류 관리 영역에서 새 분류를 추가합니다.</li><li>필요할 때 수정, 활성화, 비활성화합니다.</li><li>이미 문서나 템플릿에 사용된 분류는 삭제가 제한될 수 있습니다.</li></ol></div>'],
            ['id'=>'guide-template','title'=>'결재 템플릿 만드는 법','summary'=>'결재선, 참조자, 열람자, 제목 양식, 본문 양식을 한 번에 저장합니다.','html'=>'<div class="guide-block"><h4>템플릿에 들어가는 요소</h4><ul><li>기본 문서 분류</li><li>결재자 목록</li><li>참조자</li><li>열람자</li><li>양식 제목</li><li>양식 본문</li></ul><ol><li>회사 설정 화면으로 이동합니다.</li><li>결재선 템플릿 영역에서 템플릿명을 입력합니다.</li><li>결재선을 낮은 레벨부터 높은 레벨 순서로 선택합니다.</li><li>참조자와 열람자 기본값을 선택합니다.</li><li>제목 양식과 본문 양식을 입력합니다.</li><li>저장 후 필요하면 수정, 활성화, 비활성화, 삭제할 수 있습니다.</li></ol><div class="guide-tip"><strong>알아둘 점</strong><br>템플릿에 저장된 결재선은 실제 기안자의 레벨에 따라 제출 시 자동으로 다시 걸러집니다.</div></div>'],
            ['id'=>'guide-draft','title'=>'기안서 작성하는 법','summary'=>'템플릿 선택부터 첨부, 참조자/열람자, 임시저장, 제출까지의 흐름입니다.','html'=>'<div class="guide-block"><ol><li>기안서 작성 메뉴로 이동합니다.</li><li>문서 분류와 템플릿을 선택합니다.</li><li>템플릿에 저장된 제목/본문 양식이 자동으로 들어옵니다.</li><li>필요한 내용을 수정하고 첨부파일을 추가합니다.</li><li>참조자와 열람자를 확인 또는 수정합니다.</li><li>임시저장하거나 바로 제출합니다.</li></ol><h4>제출 전 확인할 것</h4><ul><li>문서 제목이 비어 있지 않은지</li><li>첨부파일 누락이 없는지</li><li>참조자와 열람자가 맞는지</li><li>미리보기 결재선이 의도한 흐름인지</li></ul></div>'],
            ['id'=>'guide-approval','title'=>'결재 흐름 이해하기','summary'=>'기안자의 레벨에 따라 실제 결재선이 어떻게 만들어지는지 설명합니다.','html'=>'<div class="guide-block"><h4>실제 결재선 생성 규칙</h4><ul><li>템플릿에 저장된 결재선 중 <b>기안자보다 높은 레벨만</b> 실제 결재선에 남습니다.</li><li>기안자와 같은 레벨, 더 낮은 레벨은 자동 제외됩니다.</li><li>남는 상위 결재선이 하나도 없으면 제출 즉시 자동 승인됩니다.</li></ul><table class="guide-table"><tr><th>템플릿 결재선</th><th>기안자 레벨</th><th>실제 결재선</th><th>결과</th></tr><tr><td>4 → 5 → 6</td><td>3</td><td>4 → 5 → 6</td><td>모든 결재자 승인이 필요</td></tr><tr><td>4 → 5 → 6</td><td>4</td><td>5 → 6</td><td>5, 6이 승인</td></tr><tr><td>4 → 5 → 6</td><td>5</td><td>6</td><td>6만 승인</td></tr><tr><td>4 → 5 → 6</td><td>6</td><td>없음</td><td>제출 즉시 자동 승인</td></tr></table></div>'],
            ['id'=>'guide-reject','title'=>'반려와 재기안 하는 법','summary'=>'반려 사유 확인, 수정, 재기안, 전후 비교를 확인하는 방법입니다.','html'=>'<div class="guide-block"><ol><li>반려된 문서는 반려함에서 확인합니다.</li><li>문서 상세에서 반려 사유를 읽습니다.</li><li>수정 버튼으로 제목, 본문, 첨부를 보완합니다.</li><li>다시 재기안하면 새 버전으로 결재가 다시 시작됩니다.</li><li>비교보기 화면에서 반려 전/후 내용을 확인할 수 있습니다.</li></ol><div class="guide-tip"><strong>중요</strong><br>아직 결재가 한 번도 진행되지 않은 문서는 기안자가 수정하거나 기안취소할 수 있습니다.</div></div>'],
            ['id'=>'guide-notice','title'=>'공지사항 운영하는 법','summary'=>'최상위 전체 공지, 회사 공지, 일반글, 조회수, 배너, 팝업 운용 방법입니다.','html'=>'<div class="guide-block"><ul><li><b>최상위 관리자 공지</b>: 모든 기업 공지사항 상단에 노출됩니다.</li><li><b>회사 관리자 공지</b>: 자기 회사 공지사항에서 관리합니다.</li><li><b>공지 체크</b>를 하면 목록 상단 고정 공지로 표시됩니다.</li><li>공지 체크를 하지 않으면 일반 게시글처럼 최신순으로 정렬됩니다.</li><li>배너, 팝업 옵션을 켜면 로그인 후 상단 배너 또는 팝업으로 노출할 수 있습니다.</li><li>공지와 일반글 모두 조회수가 기록됩니다.</li></ul><table class="guide-table"><tr><th>작성자</th><th>노출 범위</th><th>설명</th></tr><tr><td>최상위 관리자</td><td>전체 회사</td><td>모든 기업 공지 최상단 섹션에 표시</td></tr><tr><td>회사 관리자/공지 위임 운영자</td><td>자기 회사</td><td>자기 회사 공지만 관리 가능</td></tr></table></div>'],
            ['id'=>'guide-trash','title'=>'휴지통과 복구/비우기','summary'=>'삭제된 문서 관리, 복구, 휴지통 비우기 절차입니다.','html'=>'<div class="guide-block"><ol><li>문서를 삭제하면 바로 완전 삭제되지 않고 휴지통으로 이동합니다.</li><li>회사 관리자는 자기 회사 휴지통만 볼 수 있습니다.</li><li>최상위 관리자는 회사별 또는 전체 휴지통을 볼 수 있습니다.</li><li>필요하면 개별 복구가 가능합니다.</li><li>휴지통 비우기를 누르면 문서와 첨부파일이 영구 삭제됩니다.</li></ol><div class="guide-warn"><strong>주의</strong><br>휴지통 비우기는 되돌릴 수 없습니다.</div></div>'],
            ['id'=>'guide-notification','title'=>'알림과 독촉 기능','summary'=>'결재 대기, 반려, 완료, 독촉 재전송을 관리하는 방법입니다.','html'=>'<div class="guide-block"><ul><li>내 차례가 되면 알림함에 결재 요청 알림이 생성됩니다.</li><li>반려되면 기안자에게 반려 알림이 전달됩니다.</li><li>최종 승인되면 완료 알림이 전달됩니다.</li><li>기안자나 운영자는 문서 상세에서 독촉 기능으로 알림을 다시 보낼 수 있습니다.</li></ul><h4>자주 보는 화면</h4><ul><li>알림 메뉴: 읽지 않은 알림 수를 확인</li><li>결재 대기: 지금 내가 처리할 문서만 모아 보기</li><li>결재 대기 일수: 오래 묵은 문서를 빠르게 찾기</li></ul></div>'],
            ['id'=>'guide-password','title'=>'비밀번호 재설정','summary'=>'회사코드와 본인 정보를 확인해 재설정 링크를 발급받는 방식입니다.','html'=>'<div class="guide-block"><ol><li>비밀번호 재설정 페이지로 이동합니다.</li><li>회사코드, 이름, 이메일 또는 전화번호를 입력합니다.</li><li>일치하는 계정이 있으면 재설정 링크가 발급됩니다.</li><li>새 비밀번호를 입력해 저장합니다.</li></ol><div class="guide-tip"><strong>팁</strong><br>일반 회원과 회사 관리자는 회사코드가 틀리면 재설정이 진행되지 않습니다.</div></div>'],
            ['id'=>'guide-mobile','title'=>'모바일에서 쓰는 법','summary'=>'작은 화면에서 자주 쓰는 버튼과 빠른 흐름을 정리했습니다.','html'=>'<div class="guide-block"><ul><li>모바일에서는 좌측 메뉴가 햄버거 버튼으로 열립니다.</li><li>문서 작성, 저장, 제출, 승인, 반려 같은 주요 버튼은 하단 고정 액션 영역으로 표시됩니다.</li><li>목록은 카드 형태로 최적화되어 터치가 편합니다.</li><li>첨부파일은 용량이 크면 업로드가 늦을 수 있으니 Wi‑Fi 환경이 더 안정적입니다.</li></ul><div class="guide-tip"><strong>권장 사용 순서</strong><br>모바일에서는 승인, 반려, 공지 확인, 빠른 기안 정도로 쓰고, 템플릿/분류/권한 관리는 PC에서 처리하는 편이 낫습니다.</div></div>'],
        ];
        foreach ($sections as $index => $section): ?>
            <article class="guide-item<?= $index === 0 ? ' is-open' : '' ?>" id="<?= e($section['id']) ?>" data-accordion-item>
                <button type="button" class="guide-trigger" data-accordion-button aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>">
                    <div>
                        <strong><?= e($section['title']) ?></strong>
                        <span><?= e($section['summary']) ?></span>
                    </div>
                    <span class="guide-chevron">＋</span>
                </button>
                <div class="guide-panel" data-accordion-panel>
                    <?= $section['html'] ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
