<?php
	namespace BigTree;
?>
			</div>
		</div>
		<footer class="main">
			<section>
				<article class="bigtree">
					<a href="https://www.bigtreecms.com/" target="_blank" class="logo"></a>				
				</article>
				<article class="fastspot">
					<p>
						<?=Text::translate("Version")?> <?=BIGTREE_VERSION?>&nbsp;&nbsp;&middot;&nbsp;&nbsp;&copy; <?=date("Y")?> Fastspot
					</p>
					<a href="<?=ADMIN_ROOT?>credits/"><?=Text::translate("Credits &amp; Licenses")?></a>&nbsp;&nbsp;&middot;&nbsp;&nbsp;
					<a href="https://www.bigtreecms.org/" target="_blank"><?=Text::translate("Support")?></a>&nbsp;&nbsp;&middot;&nbsp;&nbsp;
					<a href="https://www.fastspot.com/agency/contact/" target="_blank"><?=Text::translate("Contact Us")?></a>
				</article>
			</section>
		</footer>
		<?php
			if (isset($_SESSION["bigtree_admin"]["growl"])) {
		?>
		<script>BigTree.growl("<?=Text::htmlEncode($_SESSION["bigtree_admin"]["growl"]["title"])?>","<?=Text::htmlEncode($_SESSION["bigtree_admin"]["growl"]["message"])?>",5000,"<?=htmlspecialchars($_SESSION["bigtree_admin"]["growl"]["type"])?>");</script>
		<?php
				unset($_SESSION["bigtree_admin"]["growl"]);
			}
		?>
 	</body>
</html>