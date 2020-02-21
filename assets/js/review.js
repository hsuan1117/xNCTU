function approve(uid) {
	if (!confirm('您確定要通過此貼文嗎？'))
		return;

	vote(uid, 1, '請輸入通過附註');
}

function reject(uid) {
	if (!confirm('您確定要駁回此貼文嗎？'))
		return;

	vote(uid, -1, '請輸入駁回理由');
}

function vote(uid, type, reason_prompt) {
	var login = document.querySelector('nav .right a[data-type="login"]');
	if (login) {
		alert('請先登入');
		login.click();
		return;
	}

	var reason = prompt(reason_prompt);
	if (reason === null)
		return;

	if (reason.length < 5) {
		alert('請輸入 5 個字以上');
		return;
	}


	data = {
		action: 'vote',
		uid: uid,
		vote: type,
		reason: reason
	};

	fetch('/api', {
		method: 'POST',
		body: JSON.stringify(data),
		headers: {'content-type': 'application/json'}
	}).then(resp => resp.json())
	.then((resp) => {
		console.log(resp);
		var card = document.getElementById('post-' + uid);
		if (resp.ok) {
			card.querySelector('#approvals').innerText = resp.approvals;
			card.querySelector('#rejects').innerText = resp.rejects;
		} else
			alert("Error: " + resp.msg);
	});
}
