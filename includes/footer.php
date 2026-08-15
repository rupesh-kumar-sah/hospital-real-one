        </div><!-- /.content-area -->
    </div><!-- /.main-content -->
</div><!-- /.app-layout -->

<script src="/assets/js/main.js"></script>
<?php if (isset($extraScripts)): ?>
<?php foreach ($extraScripts as $script): ?>
<script src="<?= $script ?>"></script>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
