<?php

namespace Home\Controller;

class CFController extends HomeController {
	private $host_key = 'your_Cloudflare_Partner_host_key';

	public function Index() {
		if (empty(cookie('user_key'))) {
			$this->redirect('Login');
		} else {
			$this->redirect('ZoneList');
		}
	}

	public function Login() {
		if (IS_POST) {
			$cloudflare_email = I('post.cloudflare_email', '');
			$cloudflare_pass  = I('post.cloudflare_pass', '');
			if (empty($cloudflare_email) || empty($cloudflare_pass)) {
				$this->error('登录失败：用户名、密码不能为空');
			}

			$data = array(
				'act' => 'user_create',
				'host_key' => $this->host_key,
				'cloudflare_email' => $cloudflare_email,
				'cloudflare_pass'  => $cloudflare_pass
				);

			$result = $this->post_data('https://api.cloudflare.com/host-gw.html', $data);
			if (!$result) {
				$this->error('内部错误：CURL调用失败');
			}

			$result = explode("\r\n\r\n", $result);
			$results = @json_decode($result[1], 1);
			if (!is_array($results)) {
				$this->error('内部错误：返回结果异常');
			}

			if ($results['result'] == 'error') {
				$this->error('登录失败：' . $results['msg']);
			}
			
			if (empty($results['response']['user_key'])) {
				$this->error('登录失败：用户名、密码错误');
			}

			cookie('user_key',   $results['response']['user_key']);
			cookie('user_email', $results['response']['cloudflare_email']);
			$this->success('登录成功', U('ZoneList'));
		} else {
			$this->display();
		}
	}

	public function Logout() {
		cookie(null);
		$this->success('退出成功', U('Login'));
	}

	public function ZoneList() {
		if (empty(cookie('user_key'))) {
			$this->error('请先登录', U('Login'));
		}

		$data = array(
			'act' => 'user_lookup',
			'host_key' => $this->host_key,
			'cloudflare_email' => cookie('user_email')
			);

		$result = $this->post_data('https://api.cloudflare.com/host-gw.html', $data);
		if (!$result) {
			$this->error('内部错误：CURL调用失败');
		}

		$result = explode("\r\n\r\n", $result);
		$results = @json_decode($result[1], 1);
		if (!is_array($results)) {
			$this->error('内部错误：返回结果异常');
		}

		if ($results['result'] == 'error') {
			$this->error('列出域名失败：' . $results['msg']);
		}

		$zoneList = $results['response']['hosted_zones'];
		$this->assign('zoneList', $zoneList);
		$this->display();
	}

	public function ZoneDetail($zone_name = '') {
		if (empty(cookie('user_key'))) {
			$this->error('请先登录', U('Login'));
		}
		if (empty($zone_name)) {
			$this->error('域名不能为空', U('ZoneList'));
		}

		$data = array(
			'act' => 'zone_lookup',
			'host_key' => $this->host_key,
			'user_key' => cookie('user_key'),
			'zone_name' => $zone_name
			);

		$result = $this->post_data('https://api.cloudflare.com/host-gw.html', $data);
		if (!$result) {
			$this->error('内部错误：CURL调用失败');
		}

		$result = explode("\r\n\r\n", $result);
		$results = @json_decode($result[1], 1);
		if (!is_array($results)) {
			$this->error('内部错误：返回结果异常');
		}

		if ($results['result'] == 'error') {
			$this->error('获取域名详细信息失败：' . $results['msg']);
		}

		$cnames = array_merge_recursive($results['response']['hosted_cnames'], $results['response']['forward_tos']);
		$this->assign('zone_name', $zone_name);
		$this->assign('cnames', $cnames);
		$this->assign('ssl_status', $results['response']['ssl_status']);
		$this->assign('ssl_meta_tag', $results['response']['ssl_meta_tag']);
		$this->display();
	}

	public function ZoneAdd($zone_name = '', $subdomain = '', $resolve_to = '') {
		if (empty(cookie('user_key'))) {
			$this->error('请先登录', U('Login'));
		}
		if (IS_POST) {
			if (empty($zone_name)) {
				$this->error('域名不能为空');
			}
			if (empty($resolve_to)) {
				$this->error('解析地址不能为空');
			}

			$data = array(
				'act' => 'zone_lookup',
				'host_key' => $this->host_key,
				'user_key' => cookie('user_key'),
				'zone_name' => $zone_name
				);

			$result = $this->post_data('https://api.cloudflare.com/host-gw.html', $data);
			if (!$result) {
				$this->error('内部错误：CURL调用失败');
			}

			$result = explode("\r\n\r\n", $result);
			$results = @json_decode($result[1], 1);
			if (!is_array($results)) {
				$this->error('内部错误：返回结果异常');
			}

			if ($results['result'] == 'error') {
				$this->error('获取域名详细信息失败：' . $results['msg']);
			}

			$cnames = $results['response']['hosted_cnames'];
			$new_cname = array(empty($subdomain) ? $zone_name : $subdomain . '.' . $zone_name => $resolve_to);
			if (empty($cnames)) {
				$cnames = $new_cname;
			} else {
				$cnames = array_merge($cnames, $new_cname);
			}
			unset($cnames[$zone_name]);
			$subdomains = str_replace(array("'", '"', '{', '}', '[', ']', '(', ')', "\n", ' ', "\t"), '', json_encode($cnames));

			$data = array(
				'act' => 'zone_set',
				'host_key' => $this->host_key,
				'user_key' => cookie('user_key'),
				'zone_name' => $zone_name,
				'resolve_to' => $resolve_to,
				'subdomains' => $subdomains
				);

			$result = $this->post_data('https://api.cloudflare.com/host-gw.html', $data);
			if (!$result) {
				$this->error('内部错误：CURL调用失败');
			}

			$result = explode("\r\n\r\n", $result);
			$results = @json_decode($result[1], 1);
			if (!is_array($results)) {
				$this->error('内部错误：返回结果异常');
			}

			if ($results['result'] == 'error') {
				$this->error('添加失败：' . $results['msg']);
			}
			$this->success('添加成功', U('ZoneDetail?zone_name=' . $zone_name));
		} else {
			$this->assign('zone_name', $zone_name);
			$this->display();
		}
	}

	public function ZoneEdit($zone_name = '', $subdomain = '', $resolve_to = '') {
		if (empty(cookie('user_key'))) {
			$this->error('请先登录', U('Login'));
		}
		if (empty($zone_name)) {
			$this->error('域名不能为空');
		}
		$data = array(
			'act' => 'zone_lookup',
			'host_key' => $this->host_key,
			'user_key' => cookie('user_key'),
			'zone_name' => $zone_name
			);

		$result = $this->post_data('https://api.cloudflare.com/host-gw.html', $data);
		if (!$result) {
			$this->error('内部错误：CURL调用失败');
		}

		$result = explode("\r\n\r\n", $result);
		$results = @json_decode($result[1], 1);
		if (!is_array($results)) {
			$this->error('内部错误：返回结果异常');
		}

		if ($results['result'] == 'error') {
			$this->error('获取域名详细信息失败：' . $results['msg']);
		}

		$cnames = $results['response']['hosted_cnames'];
		if (IS_POST) {
			if (empty($resolve_to)) {
				$this->error('解析地址不能为空');
			}

			$new_cname = array(empty($subdomain) ? $zone_name : $subdomain . '.' . $zone_name => $resolve_to);
			if (empty($cnames)) {
				$cnames = $new_cname;
			} else {
				$cnames = array_merge($cnames, $new_cname);
			}
			unset($cnames[$zone_name]);
			$subdomains = str_replace(array("'", '"', '{', '}', '[', ']', '(', ')', "\n", ' ', "\t"), '', json_encode($cnames));

			$data = array(
				'act' => 'zone_set',
				'host_key' => $this->host_key,
				'user_key' => cookie('user_key'),
				'zone_name' => $zone_name,
				'resolve_to' => I('post.resolve_to'),
				'subdomains' => $subdomains
				);

			$result = $this->post_data('https://api.cloudflare.com/host-gw.html', $data);
			if (!$result) {
				$this->error('内部错误：CURL调用失败');
			}

			$result = explode("\r\n\r\n", $result);
			$results = @json_decode($result[1], 1);
			if (!is_array($results)) {
				$this->error('内部错误：返回结果异常');
			}

			if ($results['result'] == 'error') {
				$this->error('修改失败：' . $results['msg']);
			}
			$this->success('修改成功', U('ZoneDetail?zone_name=' . $zone_name));
		} else {
			$this->assign('zone_name', $zone_name);
			$this->assign('subdomain', substr($subdomain, 0, strrpos($subdomain, '.' .$zone_name)));
			$this->assign('resolve_to', $cnames[$subdomain]);
			$this->display();
		}
	}

	public function ZoneDel($zone_name = '', $subdomain = '') {
		if (empty(cookie('user_key'))) {
			$this->error('请先登录', U('Login'));
		}
		if (empty($zone_name)) {
			$this->error('域名不能为空');
		}
		if (empty($subdomain)) {
			$this->error('子域不能为空');
		}

		$data = array(
			'act' => 'zone_lookup',
			'host_key' => $this->host_key,
			'user_key' => cookie('user_key'),
			'zone_name' => $zone_name
			);

		$result = $this->post_data('https://api.cloudflare.com/host-gw.html', $data);
		if (!$result) {
			$this->error('内部错误：CURL调用失败');
		}

		$result = explode("\r\n\r\n", $result);
		$results = @json_decode($result[1], 1);
		if (!is_array($results)) {
			$this->error('内部错误：返回结果异常');
		}

		if ($results['result'] == 'error') {
			$this->error('获取域名详细信息失败：' . $results['msg']);
		}

		$cnames = $results['response']['hosted_cnames'];
		if (empty($cnames[$subdomain])) {
			$this->error('不存在这个子域名');
		}

		$resolve_to = $cnames[$zone_name];
		unset($cnames[$zone_name]);
		unset($cnames[$subdomain]);
		$subdomains = str_replace(array("'", '"', '{', '}', '[', ']', '(', ')', "\n", ' ', "\t"), '', json_encode($cnames));
		$data = array(
			'act' => 'zone_set',
			'host_key' => $this->host_key,
			'user_key' => cookie('user_key'),
			'zone_name' => $zone_name,
			'resolve_to' => $resolve_to,
			'subdomains' => $subdomains
			);

		$result = $this->post_data('https://api.cloudflare.com/host-gw.html', $data);
		if (!$result) {
			$this->error('内部错误：CURL调用失败');
		}

		$result = explode("\r\n\r\n", $result);
		$results = @json_decode($result[1], 1);
		if (!is_array($results)) {
			$this->error('内部错误：返回结果异常');
		}

		if ($results['result'] == 'error') {
			$this->error('删除失败：' . $results['msg']);
		}
		$this->success('删除成功', U('ZoneDetail?zone_name=' . $zone_name));
	}

	public function ZoneDelete($zone_name = '') {
		if (empty(cookie('user_key'))) {
			$this->error('请先登录', U('Login'));
		}
		if (empty($zone_name)) {
			$this->error('域名不能为空', U('ZoneList'));
		}

		$data = array(
			'act' => 'zone_delete',
			'host_key' => $this->host_key,
			'user_key' => cookie('user_key'),
			'zone_name' => $zone_name
			);

		$result = $this->post_data('https://api.cloudflare.com/host-gw.html', $data);
		if (!$result) {
			$this->error('内部错误：CURL调用失败');
		}

		$result = explode("\r\n\r\n", $result);
		$results = @json_decode($result[1], 1);
		if (!is_array($results)) {
			$this->error('内部错误：返回结果异常');
		}

		if ($results['response']['result'] == 'error') {
			$this->error('域名不存在或未使用Cloudflare服务', U('ZoneList'));
		}

		$this->success('域名删除成功', U('ZoneList'));
	}

	private function post_data($url, $data) {
		$cookie = '';
		if ($url == '' || !is_array($data)) {
			$this->error('内部错误：参数错误');
		}

		$ch = @curl_init();
		if (!$ch) {
			$this->error('内部错误：服务器不支持CURL');
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSLVERSION, 1);
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($ch, CURLOPT_USERAGENT, 'SonicBreaker - a PHP Cloudflare Partner Suite/1.0.0');
		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
	}
}