window.addEventListener("load", init);


function init() {
	var button = document.getElementById('refresh');
	if (button)
		setInterval(updateVotes, 10*1000);
}

function approve(uid) {
	if (!confirm('您確定要通過此貼文嗎？'))
		return;

	vote(uid, 1, '通過附註');
}

function reject(uid) {
	if (!confirm('您確定要駁回此貼文嗎？'))
		return;

	vote(uid, -1, '駁回理由');
}

function vote(uid, type, reason_prompt) {
	var login = document.querySelector('nav .right a[data-type="login"]');
	if (login) {
		alert('請先登入');
		login.click();
		return;
	}

	var reason = prompt('請輸入' + reason_prompt + ' (1 - 100 字)');
	if (reason === null)
		return;

	if (reason.length < 1) {
		alert('附註請勿留空');
		return;
	}

	if (reason.length > 100) {
		alert('請輸入 100 個字以內');
		return;
	}


	var data = {
		uid: uid,
		vote: type,
		reason: reason
	};

	fetch('/api/vote', {
		method: 'POST',
		body: JSON.stringify(data),
		headers: {'content-type': 'application/json'}
	}).then(resp => resp.json())
	.then((resp) => {
		var card = document.getElementById('post-' + uid);
		if (resp.ok) {
			card.querySelector('#approvals').innerText = resp.approvals;
			card.querySelector('#rejects').innerText = resp.rejects;

			card.querySelector(".attached button.positive").classList.add("disabled");
			card.querySelector(".attached button.negative").classList.add("disabled");
		} else
			alert("Error: " + resp.msg);

		updateVotes();
	});
}

function updateVotes() {
	var button = document.getElementById('refresh');
	button.classList.add('disabled');

	var uid = document.body.dataset.uid;
	if (!uid)
		return;

	fetch('/api/votes?uid=' + uid)
	.then(resp => resp.json())
	.then((resp) => {
		if (resp.ok) {
			if (resp.reload)
				location.reload();

			if (resp.id)
				location.href = '/post/' + resp.id;

			var card = document.getElementById('post-' + uid);
			card.querySelector('#approvals').innerText = resp.approvals;
			card.querySelector('#rejects').innerText = resp.rejects;

			updateVotesTable(resp.votes);
			setTimeout(() => {
				button.classList.remove('disabled');
			}, 800);
		} else
			alert(resp.msg);
	});
}

function updateVotesTable(votes) {
	var table = document.getElementById('votes');
	var tbody = table.tBodies[0];

	var newBody = document.createElement('tbody');
	for (var i=0; i<votes.length; i++) {
		var vote = votes[i];
		var tr = voteRow(i+1, vote.vote, vote.dep, vote.name, vote.reason);
		newBody.appendChild(tr);
	}

	tbody.innerHTML = newBody.innerHTML;
}

function voteRow(no, vote, dep, name, reason) {
	var type = '❓ 未知';
	if (vote == 1)
		type = '✅ 通過';
	if (vote == -1)
		type = '❌ 駁回';

	var tr = document.createElement('tr');
	for (var i = 0; i < 5; i++)
		tr.appendChild(document.createElement('td'));

	tr.cells[0].appendChild(document.createTextNode(no));
	tr.cells[1].appendChild(document.createTextNode(type));
	tr.cells[2].appendChild(document.createTextNode(dep));
	tr.cells[3].appendChild(document.createTextNode(name));
	tr.cells[4].innerHTML = toHTML(reason);

	return tr;
}

function confirmSubmission(uid) {
	if (!confirm('確定要送出此投稿嗎？\n\n這是你反悔的最後機會'))
		return;

	document.getElementById('confirm-button').classList.add('disabled');
	document.getElementById('delete-button').classList.add('disabled');

	var data = {
		uid: uid,
		status: 'confirmed',
	};

	fetch('/api/submission', {
		method: 'PATCH',
		body: JSON.stringify(data),
		headers: {'content-type': 'application/json'}
	}).then(resp => resp.json())
	.then((resp) => {
		if (!resp.ok) {
			alert(resp.msg);
			return;
		}
		localStorage.setItem('draft', '');
		submitted = true;
		location.href = '/review/' + uid;
	});
}

function deleteSubmission(uid) {
	if (!confirm('您確定要刪除此投稿嗎？'))
		return;

	var reason = prompt('請輸入刪除附註');
	if (reason.length < 1) {
		alert('刪除附註請勿留空');
		return;
	}

	document.getElementById('confirm-button').classList.add('disabled');
	document.getElementById('delete-button').classList.add('disabled');

	var data = {
		uid: uid,
		reason: reason
	};

	fetch('/api/submission', {
		method: 'DELETE',
		body: JSON.stringify(data),
		headers: {'content-type': 'application/json'}
	}).then(resp => resp.json())
	.then((resp) => {
		alert(resp.msg);
		submitted = true;
	});
}
